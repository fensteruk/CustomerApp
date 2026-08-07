<?php

use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Controllers\NewCallOffController;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function newCallOffUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null, array $attributes = []): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()
        ->role($role)
        ->create(array_merge([
            'customer_organisation_id' => $organisation->id,
            'password' => Hash::make('password'),
        ], $attributes));
}

function newCallOffAssignedSite(User $user, array $attributes = []): Site
{
    $site = Site::factory()->create(array_merge([
        'customer_organisation_id' => $user->customer_organisation_id,
    ], $attributes));

    $user->assignedSites()->attach($site);

    return $site;
}

function newCallOffPlot(Site $site, array $attributes = []): ProjectedPlot
{
    return ProjectedPlot::factory()->create(array_merge([
        'site_id' => $site->id,
    ], $attributes));
}

it('requires authentication and a site role active site for new call off screens', function (): void {
    $this->get('/portal/call-offs/new')
        ->assertRedirect('/login');

    $officeUser = newCallOffUser(PortalRoleIdentifier::FensterOfficeStaff);
    $site = newCallOffAssignedSite($officeUser);

    $this->actingAs($officeUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/call-offs/new')
        ->assertForbidden();
});

it('shows only eligible projected plots for the active assigned site and selected services', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::SiteManager);
    $site = newCallOffAssignedSite($user, ['name' => 'Maple Rise']);

    newCallOffPlot($site, ['plot_reference' => 'Plot 101']);
    $conflictedPlot = newCallOffPlot($site, ['plot_reference' => 'Plot 102']);
    newCallOffPlot($site, ['plot_reference' => 'Completed Plot', 'is_completed' => true]);

    $otherSite = Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
    ]);
    newCallOffPlot($otherSite, ['plot_reference' => 'Hidden Other Site Plot']);

    foreach (CallOffServiceType::cases() as $serviceType) {
        app(SubmitCallOffBatchAction::class)->handle(
            user: $user,
            site: $site,
            serviceType: $serviceType,
            requestedDate: Carbon::today()->addDays(10),
            projectedPlots: [$conflictedPlot],
        );
    }

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/call-offs/new')
        ->assertOk()
        ->assertSee('New Call Off')
        ->assertSee('Maple Rise')
        ->assertSee('Service Type')
        ->assertSee('Requested Date')
        ->assertSee('Projected Plot Selection')
        ->assertSee('Plot 101')
        ->assertDontSee('Plot 102')
        ->assertDontSee('Completed Plot')
        ->assertDontSee('Hidden Other Site Plot');
});

it('shows validation feedback before the confirmation screen', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::AssistantSiteManager);
    $site = newCallOffAssignedSite($user);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from('/portal/call-offs/new')
        ->post('/portal/call-offs/confirm', [
            'service_identifier' => '',
            'requested_date' => '',
            'projected_plots' => [],
        ])
        ->assertRedirect('/portal/call-offs/new')
        ->assertSessionHasErrors(['service_identifier', 'requested_date', 'projected_plots']);
});

it('reviews a call off without creating the batch', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::FinishingForeman);
    $site = newCallOffAssignedSite($user, ['name' => 'Oak View']);
    $firstPlot = newCallOffPlot($site, ['plot_reference' => 'Plot 201']);
    $secondPlot = newCallOffPlot($site, ['plot_reference' => 'Plot 202']);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/confirm', [
            'service_identifier' => CallOffServiceType::Windows->value,
            'requested_date' => Carbon::today()->addDays(14)->toDateString(),
            'projected_plots' => [$firstPlot->uuid, $secondPlot->uuid],
            'customer_response' => 'Please confirm the requested date.',
        ])
        ->assertOk()
        ->assertSee('Review Call Off')
        ->assertSee('Oak View')
        ->assertSee('Windows')
        ->assertSee('Plot 201')
        ->assertSee('Plot 202')
        ->assertSee('Please confirm the requested date.');

    expect(CallOffBatch::query()->count())->toBe(0)
        ->and(CallOffRequest::query()->count())->toBe(0);
});

it('submits one batch with one request per selected projected plot', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::SiteManager, attributes: ['name' => 'Jordan Site']);
    $site = newCallOffAssignedSite($user, ['name' => 'Elm Gardens']);
    $firstPlot = newCallOffPlot($site, ['plot_reference' => 'Plot 301']);
    $secondPlot = newCallOffPlot($site, ['plot_reference' => 'Plot 302']);
    $requestedDate = Carbon::today()->addDays(21);
    $payload = [
        'service_identifier' => CallOffServiceType::CavityClosers->value,
        'requested_date' => $requestedDate->toDateString(),
        'projected_plots' => [$firstPlot->uuid, $secondPlot->uuid],
        'customer_response' => 'Batch request for both plots.',
    ];

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/confirm', $payload)
        ->assertOk();

    $signature = session(NewCallOffController::CONFIRMATION_SIGNATURE_SESSION_KEY);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs', $payload + ['confirmation_signature' => $signature])
        ->assertRedirect('/portal/site-dashboard')
        ->assertSessionHas('status', 'Call-off submitted for 2 projected plots.');

    $batch = CallOffBatch::query()->first();

    expect($batch)->not->toBeNull()
        ->and($batch->site_id)->toBe($site->id)
        ->and($batch->submitted_by_user_id)->toBe($user->id)
        ->and($batch->service_identifier)->toBe(CallOffServiceType::CavityClosers)
        ->and($batch->requested_date->toDateString())->toBe($requestedDate->toDateString())
        ->and($batch->customer_response)->toBe('Batch request for both plots.')
        ->and(CallOffRequest::query()->where('call_off_batch_id', $batch->id)->count())->toBe(2);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('Plot 301')
        ->assertSee('Plot 302')
        ->assertSee('Cavity Closers')
        ->assertSee('Submitted')
        ->assertSee('Jordan Site');
});

it('rejects final submission without the confirmation screen', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::AssistantSiteManager);
    $site = newCallOffAssignedSite($user);
    $plot = newCallOffPlot($site);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from('/portal/call-offs/new')
        ->post('/portal/call-offs', [
            'service_identifier' => CallOffServiceType::Windows->value,
            'requested_date' => Carbon::today()->addDays(14)->toDateString(),
            'projected_plots' => [$plot->uuid],
        ])
        ->assertRedirect('/portal/call-offs/new')
        ->assertSessionHasErrors('request');

    expect(CallOffBatch::query()->count())->toBe(0)
        ->and(CallOffRequest::query()->count())->toBe(0);
});

it('detects tampered confirmation data before final submission', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::FinishingForeman);
    $site = newCallOffAssignedSite($user);
    $plot = newCallOffPlot($site);
    $payload = [
        'service_identifier' => CallOffServiceType::Windows->value,
        'requested_date' => Carbon::today()->addDays(14)->toDateString(),
        'projected_plots' => [$plot->uuid],
        'customer_response' => 'Original reviewed message.',
    ];

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/confirm', $payload)
        ->assertOk();

    $signature = session(NewCallOffController::CONFIRMATION_SIGNATURE_SESSION_KEY);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from('/portal/call-offs/new')
        ->post('/portal/call-offs', array_merge($payload, [
            'requested_date' => Carbon::today()->addDays(21)->toDateString(),
            'confirmation_signature' => $signature,
        ]))
        ->assertRedirect('/portal/call-offs/new')
        ->assertSessionHasErrors('request');

    expect(CallOffBatch::query()->count())->toBe(0)
        ->and(CallOffRequest::query()->count())->toBe(0);
});

it('rejects tampered projected plot uuids outside the active site', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::SiteManager);
    $site = newCallOffAssignedSite($user);
    $otherSite = Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
    ]);
    $otherSitePlot = newCallOffPlot($otherSite);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from('/portal/call-offs/new')
        ->post('/portal/call-offs/confirm', [
            'service_identifier' => CallOffServiceType::Windows->value,
            'requested_date' => Carbon::today()->addDays(14)->toDateString(),
            'projected_plots' => [$otherSitePlot->uuid],
        ])
        ->assertRedirect('/portal/call-offs/new')
        ->assertSessionHasErrors('projected_plots');

    expect(CallOffBatch::query()->count())->toBe(0)
        ->and(CallOffRequest::query()->count())->toBe(0);
});

it('rechecks eligibility on final submit and prevents duplicate active submissions', function (): void {
    $user = newCallOffUser(PortalRoleIdentifier::SiteManager);
    $site = newCallOffAssignedSite($user);
    $plot = newCallOffPlot($site);
    $payload = [
        'service_identifier' => CallOffServiceType::Windows->value,
        'requested_date' => Carbon::today()->addDays(14)->toDateString(),
        'projected_plots' => [$plot->uuid],
    ];

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/confirm', $payload)
        ->assertOk();

    $signature = session(NewCallOffController::CONFIRMATION_SIGNATURE_SESSION_KEY);

    app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: CallOffServiceType::Windows,
        requestedDate: Carbon::today()->addDays(15),
        projectedPlots: [$plot],
    );

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from('/portal/call-offs/new')
        ->post('/portal/call-offs', $payload + ['confirmation_signature' => $signature])
        ->assertRedirect('/portal/call-offs/new')
        ->assertSessionHasErrors('projected_plots');

    expect(CallOffBatch::query()->count())->toBe(1)
        ->and(CallOffRequest::query()->count())->toBe(1);
});
