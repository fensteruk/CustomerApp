<?php

use App\Actions\CallOff\ApproveCallOffRequestAction;
use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Actions\CallOff\WithdrawCallOffRequestsAction;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function reviewUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null, array $attributes = []): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()
        ->role($role)
        ->create(array_merge([
            'customer_organisation_id' => $organisation->id,
            'password' => Hash::make('password'),
        ], $attributes));
}

function reviewAssignedSite(User $user, array $attributes = []): Site
{
    $site = Site::factory()->create(array_merge([
        'customer_organisation_id' => $user->customer_organisation_id,
    ], $attributes));

    $user->assignedSites()->attach($site);

    return $site;
}

function reviewPlot(Site $site, array $attributes = []): ProjectedPlot
{
    return ProjectedPlot::factory()->create(array_merge([
        'site_id' => $site->id,
    ], $attributes));
}

function reviewRequestFor(
    User $siteUser,
    Site $site,
    string $plotReference = 'Plot 101',
    CallOffServiceType $serviceType = CallOffServiceType::Windows,
    ?string $customerResponse = 'Please book this in.',
): CallOffRequest {
    $plot = reviewPlot($site, ['plot_reference' => $plotReference]);

    return app(SubmitCallOffBatchAction::class)->handle(
        user: $siteUser,
        site: $site,
        serviceType: $serviceType,
        requestedDate: Carbon::today()->addDays(10)->toDateString(),
        projectedPlots: [$plot],
        customerResponse: $customerResponse,
    )->requests->first();
}

function reviewPeople(): array
{
    $organisation = CustomerOrganisation::factory()->create(['name' => 'Acme Homes']);
    $siteUser = reviewUser(PortalRoleIdentifier::SiteManager, $organisation, ['name' => 'Sam Site']);
    $officeUser = reviewUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation, ['name' => 'Olivia Office']);
    $site = reviewAssignedSite($siteUser, ['name' => 'Maple Rise']);
    $officeUser->assignedSites()->attach($site);

    return [$organisation, $siteUser, $officeUser, $site];
}

it('shows submitted requests to global Office Staff', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $request = reviewRequestFor($siteUser, $site, 'Plot 201', CallOffServiceType::CavityClosers, 'Ready from Monday.');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertSee('Review Requests')
        ->assertSee('Maple Rise')
        ->assertSee('Plot 201')
        ->assertSee('Cavity Closers')
        ->assertSee($request->batch->requested_date->format('j M Y'))
        ->assertSee('Sam Site')
        ->assertSee('Ready from Monday.')
        ->assertSee('Submitted');
});

it('shows requests from formerly unassigned sites to Office Staff', function (): void {
    [$organisation, $siteUser, $officeUser] = reviewPeople();
    $hiddenSite = Site::factory()->create([
        'customer_organisation_id' => $organisation->id,
        'name' => 'Hidden Review Site',
    ]);
    $siteUser->assignedSites()->attach($hiddenSite);
    reviewRequestFor($siteUser, $hiddenSite, 'Hidden Plot');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertSee('Hidden Review Site')
        ->assertSee('Hidden Plot');
});

it('blocks site roles and guests from review screens and decision actions', function (): void {
    [, $siteUser, , $site] = reviewPeople();
    $request = reviewRequestFor($siteUser, $site);

    $this->get('/portal/review-requests')->assertRedirect('/login');
    $this->get('/portal/review-requests/'.$request->uuid)->assertRedirect('/login');
    $this->post('/portal/review-requests/'.$request->uuid.'/approve')->assertRedirect('/login');

    $this->actingAs($siteUser)
        ->get('/portal/review-requests')
        ->assertForbidden();

    $this->actingAs($siteUser)
        ->post('/portal/review-requests/'.$request->uuid.'/approve')
        ->assertForbidden();
});

it('shows authorised review detail with Office Staff history', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $request = reviewRequestFor($siteUser, $site, 'Plot 301');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests/'.$request->uuid)
        ->assertOk()
        ->assertSee('Review Plot 301')
        ->assertSee('Acme Homes')
        ->assertSee('Maple Rise')
        ->assertSee('Windows')
        ->assertSee('Sam Site')
        ->assertSee('Submitted')
        ->assertSee('Please book this in.');
});

it('allows global Office Staff detail access across customers and sites', function (): void {
    [$organisation, $siteUser, $officeUser] = reviewPeople();
    $sameOrganisationHiddenSite = Site::factory()->create([
        'customer_organisation_id' => $organisation->id,
    ]);
    $siteUser->assignedSites()->attach($sameOrganisationHiddenSite);
    $sameOrganisationRequest = reviewRequestFor($siteUser, $sameOrganisationHiddenSite);

    $otherOrganisation = CustomerOrganisation::factory()->create();
    $otherSiteUser = reviewUser(PortalRoleIdentifier::SiteManager, $otherOrganisation);
    $otherSite = reviewAssignedSite($otherSiteUser);
    $otherRequest = reviewRequestFor($otherSiteUser, $otherSite);

    $this->actingAs($officeUser)
        ->get('/portal/review-requests/'.$sameOrganisationRequest->uuid)
        ->assertOk();

    $this->actingAs($officeUser)
        ->get('/portal/review-requests/'.$otherRequest->uuid)
        ->assertOk();
});

it('allows assigned Office Staff to approve with a customer response', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $request = reviewRequestFor($siteUser, $site, 'Plot 401');

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$request->uuid.'/approve', [
            'customer_response' => 'Approved for the requested date.',
            'internal_reason' => 'Capacity checked.',
        ])
        ->assertRedirect('/portal/review-requests')
        ->assertSessionHas('status');

    $history = $request->fresh()->histories()->where('event_type', CallOffHistoryEventType::Approved)->firstOrFail();

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Approved)
        ->and($history->customer_response)->toBe('Approved for the requested date.')
        ->and($history->internal_reason)->toBe('Capacity checked.');
});

it('allows assigned Office Staff to reject with required customer response', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $request = reviewRequestFor($siteUser, $site, 'Plot 501');

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$request->uuid.'/reject', [
            'customer_response' => 'Please choose another date.',
            'internal_reason' => 'Requested date unavailable.',
        ])
        ->assertRedirect('/portal/review-requests')
        ->assertSessionHas('status');

    $history = $request->fresh()->histories()->where('event_type', CallOffHistoryEventType::Rejected)->firstOrFail();

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Rejected)
        ->and($history->customer_response)->toBe('Please choose another date.')
        ->and($history->internal_reason)->toBe('Requested date unavailable.');
});

it('requires customer-visible response when rejecting', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $request = reviewRequestFor($siteUser, $site, 'Plot 601');

    $this->actingAs($officeUser)
        ->from('/portal/review-requests/'.$request->uuid)
        ->post('/portal/review-requests/'.$request->uuid.'/reject', [
            'customer_response' => '',
        ])
        ->assertRedirect('/portal/review-requests/'.$request->uuid)
        ->assertSessionHasErrors('customer_response');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Submitted);
});

it('removes approved and rejected requests from the default submitted queue', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $approvedRequest = reviewRequestFor($siteUser, $site, 'Plot 701');
    $rejectedRequest = reviewRequestFor($siteUser, $site, 'Plot 702');

    app(ApproveCallOffRequestAction::class)->handle($officeUser, $approvedRequest, 'Approved.');
    app(RejectCallOffRequestAction::class)->handle($officeUser, $rejectedRequest, 'Rejected.');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertDontSee('Plot 701')
        ->assertDontSee('Plot 702');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests?status=approved')
        ->assertOk()
        ->assertSee('Plot 701')
        ->assertDontSee('Plot 702');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests?status=rejected')
        ->assertOk()
        ->assertSee('Plot 702')
        ->assertDontSee('Plot 701');
});

it('updates the site-user dashboard after approval and rejection without exposing internal reason', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $approvedRequest = reviewRequestFor($siteUser, $site, 'Plot 801');
    $rejectedRequest = reviewRequestFor($siteUser, $site, 'Plot 802');

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$approvedRequest->uuid.'/approve', [
            'customer_response' => 'Approved customer response.',
            'internal_reason' => 'Private approval note.',
        ]);

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$rejectedRequest->uuid.'/reject', [
            'customer_response' => 'Rejected customer response.',
            'internal_reason' => 'Private rejection note.',
        ]);

    $this->actingAs($siteUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('Plot 801')
        ->assertSee('Date Agreed')
        ->assertSee('Plot 802')
        ->assertSee('Not Called Off')
        ->assertDontSee('Approved customer response.')
        ->assertDontSee('Rejected customer response.')
        ->assertDontSee('Private approval note')
        ->assertDontSee('Private rejection note');
});

it('fails stale and conflicting decisions safely', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $approvedRequest = reviewRequestFor($siteUser, $site, 'Plot 901');
    $rejectedRequest = reviewRequestFor($siteUser, $site, 'Plot 902');

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$approvedRequest->uuid.'/approve', [
            'customer_response' => 'Approved once.',
        ]);

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$approvedRequest->uuid.'/approve', [
            'customer_response' => 'Approved twice.',
        ])
        ->assertRedirect('/portal/review-requests/'.$approvedRequest->uuid)
        ->assertSessionHasErrors('status');

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$approvedRequest->uuid.'/reject', [
            'customer_response' => 'Reject after approval.',
        ])
        ->assertRedirect('/portal/review-requests/'.$approvedRequest->uuid)
        ->assertSessionHasErrors('status');

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$rejectedRequest->uuid.'/reject', [
            'customer_response' => 'Rejected once.',
        ]);

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$rejectedRequest->uuid.'/approve', [
            'customer_response' => 'Approve after rejection.',
        ])
        ->assertRedirect('/portal/review-requests/'.$rejectedRequest->uuid)
        ->assertSessionHasErrors('status');

    expect(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::Approved)->count())->toBe(1)
        ->and(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::Rejected)->count())->toBe(1);
});

it('prevents deciding withdrawn requests while Office Staff assignments do not affect global access', function (): void {
    [, $siteUser, $officeUser, $site] = reviewPeople();
    $withdrawnRequest = reviewRequestFor($siteUser, $site, 'Plot 1001');
    $revokedRequest = reviewRequestFor($siteUser, $site, 'Plot 1002');

    app(WithdrawCallOffRequestsAction::class)->handle($siteUser, [$withdrawnRequest]);

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$withdrawnRequest->uuid.'/approve')
        ->assertRedirect('/portal/review-requests/'.$withdrawnRequest->uuid)
        ->assertSessionHasErrors('status');

    $officeUser->assignedSites()->detach($site);

    $this->actingAs($officeUser)
        ->post('/portal/review-requests/'.$revokedRequest->uuid.'/approve', ['customer_response' => 'Approved globally.'])
        ->assertRedirect('/portal/review-requests')
        ->assertSessionHas('status');

    expect($withdrawnRequest->fresh()->status)->toBe(CallOffRequestStatus::Withdrawn)
        ->and($revokedRequest->fresh()->status)->toBe(CallOffRequestStatus::Approved);
});

it('fails malformed UUIDs safely', function (): void {
    $officeUser = reviewUser(PortalRoleIdentifier::FensterOfficeStaff);

    $this->actingAs($officeUser)
        ->get('/portal/review-requests/not-a-uuid')
        ->assertNotFound();
});

it('keeps global Office Staff filters server-side', function (): void {
    [$organisation, $siteUser, $officeUser, $assignedSite] = reviewPeople();
    reviewRequestFor($siteUser, $assignedSite, 'Visible Filter Plot', CallOffServiceType::Windows);

    $hiddenSite = Site::factory()->create([
        'customer_organisation_id' => $organisation->id,
        'name' => 'Filtered Hidden Site',
    ]);
    $siteUser->assignedSites()->attach($hiddenSite);
    reviewRequestFor($siteUser, $hiddenSite, 'Hidden Filter Plot', CallOffServiceType::Cml);

    $this->actingAs($officeUser)
        ->get('/portal/review-requests?status=submitted&site='.$hiddenSite->id.'&service=cml')
        ->assertOk()
        ->assertSee('Hidden Filter Plot')
        ->assertSee('Filtered Hidden Site');

    $this->actingAs($officeUser)
        ->get('/portal/review-requests?status=submitted&site='.$assignedSite->id.'&service=windows')
        ->assertOk()
        ->assertSee('Visible Filter Plot')
        ->assertDontSee('Hidden Filter Plot');
});
