<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\PortalNotificationLinkService;
use App\Services\PortalNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('enforces the complete-profile authentication matrix', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);

    $nullOrganisationOffice = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff, null, [
        'password' => 'office-password',
    ]);
    $historicalOffice = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff, $customer);
    $inactiveOffice = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff, null, ['is_active' => false]);

    expect($nullOrganisationOffice->hasCompletePortalProfile())->toBeTrue()
        ->and($historicalOffice->hasCompletePortalProfile())->toBeTrue()
        ->and($inactiveOffice->hasCompletePortalProfile())->toBeFalse();

    $this->post('/login', ['email' => $nullOrganisationOffice->email, 'password' => 'office-password'])
        ->assertRedirect('/dashboard');
    $this->post('/logout');

    foreach (PortalRoleIdentifier::siteRoles() as $role) {
        $validSiteUser = officeOrganisationQaUser($role, $customer);
        $validSiteUser->assignedSites()->attach($site);
        $invalidSiteUser = officeOrganisationQaUser($role, null);

        expect($validSiteUser->hasCompletePortalProfile())->toBeTrue()
            ->and($invalidSiteUser->hasCompletePortalProfile())->toBeFalse();

        $this->post('/login', ['email' => $validSiteUser->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->post('/logout');

        $this->post('/login', ['email' => $invalidSiteUser->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    $unknownRole = PortalRole::query()->create(['identifier' => 'unknown_role', 'name' => 'Unknown role']);
    $unknownUser = User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => $unknownRole->id,
    ]);

    expect($unknownUser->hasCompletePortalProfile())->toBeFalse();

    $this->post('/login', ['email' => $inactiveOffice->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $unknownUser->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('keeps an organisation-backed site user without assignments away from every site', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create();
    $user = officeOrganisationQaUser($role, $customer);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);

    expect($user->hasCompletePortalProfile())->toBeTrue()
        ->and($user->canAccessSite($site))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view-projected-plot', $plot))->toBeFalse();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->get('/sites/select')
        ->assertOk()
        ->assertSee('No assigned sites');

    $this->actingAs($user)
        ->post('/sites/active', ['site_id' => $site->id])
        ->assertForbidden();
})->with(PortalRoleIdentifier::siteRoles());

it('gives null-organisation Office Staff global request and plot authority across customers', function (): void {
    $office = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff);
    [$firstSiteUser, $firstSite, $firstPlot, $firstRequest] = officeOrganisationQaRequest('Customer A', 'Site A', 'Plot A');
    [, $secondSite, $secondPlot, $secondRequest] = officeOrganisationQaRequest('Customer B', 'Site B', 'Plot B');

    expect($office->customer_organisation_id)->toBeNull()
        ->and(Gate::forUser($office)->allows('view-projected-plot', $firstPlot))->toBeTrue()
        ->and(Gate::forUser($office)->allows('view-projected-plot', $secondPlot))->toBeTrue()
        ->and(Gate::forUser($office)->allows('review-call-off', $firstRequest))->toBeTrue()
        ->and(Gate::forUser($office)->allows('review-call-off', $secondRequest))->toBeTrue()
        ->and(Gate::forUser($office)->allows('manage-portal-accounts'))->toBeTrue()
        ->and(Gate::forUser($office)->allows('manage-site-assignments'))->toBeTrue();

    $this->actingAs($office)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertSee($firstSite->name)
        ->assertSee($secondSite->name)
        ->assertSee($firstPlot->plot_reference)
        ->assertSee($secondPlot->plot_reference);

    $this->actingAs($office)->get(route('portal.review-requests.show', $firstRequest))->assertOk();
    $this->actingAs($office)->get(route('portal.review-requests.show', $secondRequest))->assertOk();

    app(AgreeRequestedCallOffDateAction::class)->handle($office, $firstRequest);

    expect($firstRequest->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($firstSiteUser->fresh()->customer_organisation_id)->toBe($firstSite->customer_organisation_id);
});

it('blocks direct cross-customer and same-customer unassigned UUID access for every external role', function (PortalRoleIdentifier $role): void {
    $customerA = CustomerOrganisation::factory()->create();
    $customerB = CustomerOrganisation::factory()->create();
    $siteA1 = Site::factory()->create(['customer_organisation_id' => $customerA->id]);
    $siteA2 = Site::factory()->create(['customer_organisation_id' => $customerA->id]);
    $siteB = Site::factory()->create(['customer_organisation_id' => $customerB->id]);
    $user = officeOrganisationQaUser($role, $customerA);
    $user->assignedSites()->attach($siteA1);
    $plotA2 = ProjectedPlot::factory()->create(['site_id' => $siteA2->id]);
    $plotB = ProjectedPlot::factory()->create(['site_id' => $siteB->id]);
    $requestB = officeOrganisationQaRequestFor($user, $siteB, $plotB);

    expect(Gate::forUser($user)->allows('view-projected-plot', $plotA2))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view-projected-plot', $plotB))->toBeFalse()
        ->and($user->canAccessSite($siteA2))->toBeFalse()
        ->and($user->canAccessSite($siteB))->toBeFalse();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $siteA1->id])
        ->get(route('portal.plots.show', $plotA2))
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $siteA1->id])
        ->get(route('portal.plots.show', $plotB))
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $siteA1->id])
        ->get(route('portal.call-offs.show', $requestB))
        ->assertNotFound();
})->with(PortalRoleIdentifier::siteRoles());

it('never treats a null organisation as an Office Staff wildcard', function (PortalRoleIdentifier $role): void {
    [, , $plot, $request] = officeOrganisationQaRequest('Protected customer', 'Protected site', 'Protected plot');
    $attacker = officeOrganisationQaUser($role);

    expect($attacker->customer_organisation_id)->toBeNull()
        ->and($attacker->hasCompletePortalProfile())->toBeFalse()
        ->and(Gate::forUser($attacker)->allows('view-projected-plot', $plot))->toBeFalse()
        ->and(Gate::forUser($attacker)->allows('review-call-off', $request))->toBeFalse()
        ->and(Gate::forUser($attacker)->allows('manage-portal-accounts'))->toBeFalse()
        ->and(Gate::forUser($attacker)->allows('manage-site-assignments'))->toBeFalse();
})->with(PortalRoleIdentifier::siteRoles());

it('keeps Sprint 3E Office actions global and customer actions site-scoped', function (): void {
    [$siteUser, $site, , $request] = officeOrganisationQaRequest('Negotiation customer', 'Negotiation site', 'Negotiation plot');
    $office = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle(
        $office,
        $request,
        officeOrganisationQaFutureWeekday(),
    );

    $wrongCustomer = CustomerOrganisation::factory()->create();
    $wrongSite = Site::factory()->create(['customer_organisation_id' => $wrongCustomer->id]);
    $wrongCustomerUser = officeOrganisationQaUser(PortalRoleIdentifier::SiteManager, $wrongCustomer);
    $wrongCustomerUser->assignedSites()->attach($wrongSite);

    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($wrongCustomerUser, $request->fresh(), $proposal))
        ->toThrow(AuthorizationException::class);

    app(AcceptAlternativeCallOffDateAction::class)->handle($siteUser, $request->fresh(), $proposal);

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($office->customer_organisation_id)->toBeNull()
        ->and($siteUser->canAccessSite($site))->toBeTrue();
});

it('notifies active null-organisation Office Staff without notifying inactive or external null profiles', function (): void {
    [, , , $request] = officeOrganisationQaRequest('Notify customer', 'Notify site', 'Notify plot');
    $activeOffice = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff);
    $inactiveOffice = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff, null, ['is_active' => false]);
    $invalidExternal = officeOrganisationQaUser(PortalRoleIdentifier::SiteManager);

    app(PortalNotificationService::class)->createForRequest($request, PortalNotificationType::CallOffSubmitted);

    expect(PortalNotification::query()->where('notifiable_user_id', $activeOffice->id)->exists())->toBeTrue()
        ->and(PortalNotification::query()->where('notifiable_user_id', $inactiveOffice->id)->exists())->toBeFalse()
        ->and(PortalNotification::query()->where('notifiable_user_id', $invalidExternal->id)->exists())->toBeFalse();

    $notification = PortalNotification::query()->where('notifiable_user_id', $activeOffice->id)->firstOrFail();
    expect(app(PortalNotificationLinkService::class)->resolve($activeOffice, $notification))->not->toBeNull();
});

it('invalidates Office authority immediately after a role change leaves a null external profile', function (): void {
    [, , $plot, $request] = officeOrganisationQaRequest('Role customer', 'Role site', 'Role plot');
    $office = officeOrganisationQaUser(PortalRoleIdentifier::FensterOfficeStaff);

    expect($office->hasCompletePortalProfile())->toBeTrue()
        ->and(Gate::forUser($office)->allows('review-call-off', $request))->toBeTrue();

    $siteRole = PortalRole::query()->where('identifier', PortalRoleIdentifier::SiteManager->value)->firstOrFail();
    $office->update(['portal_role_id' => $siteRole->id]);

    expect($office->hasCompletePortalProfile())->toBeFalse()
        ->and($office->isFensterOfficeStaff())->toBeFalse()
        ->and(Gate::forUser($office)->allows('view-projected-plot', $plot))->toBeFalse()
        ->and(Gate::forUser($office)->allows('review-call-off', $request))->toBeFalse()
        ->and(Gate::forUser($office)->allows('manage-portal-accounts'))->toBeFalse();

    $this->actingAs($office)
        ->get('/portal/review-requests')
        ->assertRedirect('/login');
});

it('provisions Office Staff without a fake customer and preserves password and email controls', function (): void {
    $customerCount = CustomerOrganisation::query()->count();
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'name' => 'QA Office User',
        'email' => 'qa-office@example.test',
        'password' => 'strong-test-password',
        'is_active' => true,
    ]);

    expect($office->customer_organisation_id)->toBeNull()
        ->and(CustomerOrganisation::query()->count())->toBe($customerCount)
        ->and(Hash::check('strong-test-password', $office->password))->toBeTrue()
        ->and($office->hasCompletePortalProfile())->toBeTrue();

    expect(fn () => User::factory()->create(['email' => $office->email]))
        ->toThrow(QueryException::class);
});

it('requires authentication for protected Office and customer routes', function (): void {
    [, , $plot, $request] = officeOrganisationQaRequest('Guest customer', 'Guest site', 'Guest plot');

    $this->get('/portal/review-requests')->assertRedirect('/login');
    $this->get(route('portal.review-requests.show', $request))->assertRedirect('/login');
    $this->get(route('portal.plots.show', $plot))->assertRedirect('/login');
});

function officeOrganisationQaUser(
    PortalRoleIdentifier $role,
    ?CustomerOrganisation $organisation = null,
    array $attributes = [],
): User {
    $values = array_merge([
        'customer_organisation_id' => $organisation?->id,
        'password' => 'password',
    ], $attributes);

    return User::factory()->role($role)->create($values);
}

/** @return array{User, Site, ProjectedPlot, CallOffRequest} */
function officeOrganisationQaRequest(string $customerName, string $siteName, string $plotReference): array
{
    $customer = CustomerOrganisation::factory()->create(['name' => $customerName]);
    $site = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => $siteName,
    ]);
    $siteUser = officeOrganisationQaUser(PortalRoleIdentifier::SiteManager, $customer);
    $siteUser->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'plot_reference' => $plotReference,
    ]);

    return [$siteUser, $site, $plot, officeOrganisationQaRequestFor($siteUser, $site, $plot)];
}

function officeOrganisationQaRequestFor(User $submitter, Site $site, ProjectedPlot $plot): CallOffRequest
{
    $service = ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
        'source_present' => true,
    ]);
    $requestedDate = officeOrganisationQaFutureWeekday();
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $submitter->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => $requestedDate,
    ]);

    return CallOffRequest::query()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => $requestedDate,
        'normal_earliest_date' => $requestedDate,
        'status' => CallOffRequestStatus::AwaitingFenster,
    ]);
}

function officeOrganisationQaFutureWeekday(): string
{
    return CarbonImmutable::today()->addWeeks(2)->nextWeekday()->toDateString();
}
