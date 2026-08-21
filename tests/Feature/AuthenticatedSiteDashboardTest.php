<?php

use App\Actions\CallOff\ApproveCallOffRequestAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function dashboardUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null, array $attributes = []): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()
        ->role($role)
        ->create(array_merge([
            'customer_organisation_id' => $organisation->id,
            'password' => Hash::make('password'),
        ], $attributes));
}

function dashboardAssignedSite(User $user, array $attributes = []): Site
{
    $site = Site::factory()->create(array_merge([
        'customer_organisation_id' => $user->customer_organisation_id,
    ], $attributes));

    $user->assignedSites()->attach($site);

    return $site;
}

function dashboardPlot(Site $site, array $attributes = []): ProjectedPlot
{
    return ProjectedPlot::factory()->create(array_merge([
        'site_id' => $site->id,
    ], $attributes));
}

it('shows assigned site selection with only the user authorised sites', function (): void {
    $user = dashboardUser(PortalRoleIdentifier::SiteManager);
    $assignedSite = dashboardAssignedSite($user, ['name' => 'Assigned Alpha', 'location' => 'Bristol']);
    dashboardPlot($assignedSite);
    dashboardPlot($assignedSite);

    Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
        'name' => 'Hidden Beta',
    ]);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $assignedSite->id])
        ->get('/sites/select')
        ->assertOk()
        ->assertSee('Assigned sites')
        ->assertSee('Current active site')
        ->assertSee('Assigned Alpha')
        ->assertSee('2 outstanding projected plots')
        ->assertDontSee('Hidden Beta');
});

it('shows a clear empty state when a site user has no assigned sites', function (): void {
    $user = dashboardUser(PortalRoleIdentifier::AssistantSiteManager);

    $this->actingAs($user)
        ->get('/sites/select')
        ->assertOk()
        ->assertSee('No assigned sites')
        ->assertSee('Fenster will need to assign a site');
});

it('shows the authenticated site dashboard with real projected plots and call-offs', function (): void {
    $organisation = CustomerOrganisation::factory()->create(['name' => 'Acme Homes']);
    $siteUser = dashboardUser(PortalRoleIdentifier::FinishingForeman, $organisation, ['name' => 'Jordan Site']);
    $site = dashboardAssignedSite($siteUser, ['name' => 'Maple Rise']);
    $officeUser = dashboardUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation, ['name' => 'Fenster Office']);
    $officeUser->assignedSites()->attach($site);

    $submittedPlot = dashboardPlot($site, ['plot_reference' => 'Plot 101']);
    $approvedPlot = dashboardPlot($site, ['plot_reference' => 'Plot 102']);
    dashboardPlot($site, ['plot_reference' => 'Completed Plot', 'is_completed' => true]);

    app(SubmitCallOffBatchAction::class)->handle(
        user: $siteUser,
        site: $site,
        serviceType: CallOffServiceType::Windows,
        requestedDate: '2026-08-20',
        projectedPlots: [$submittedPlot],
    );

    $approvedBatch = app(SubmitCallOffBatchAction::class)->handle(
        user: $siteUser,
        site: $site,
        serviceType: CallOffServiceType::CavityClosers,
        requestedDate: '2026-08-24',
        projectedPlots: [$approvedPlot],
    );

    app(ApproveCallOffRequestAction::class)->handle(
        actor: $officeUser,
        request: $approvedBatch->requests->first(),
        customerResponse: 'Approved for the requested date.',
        internalReason: 'Private office reason',
    );

    $otherSite = Site::factory()->create([
        'customer_organisation_id' => $organisation->id,
        'name' => 'Hidden Site',
    ]);
    dashboardPlot($otherSite, ['plot_reference' => 'Hidden Plot']);

    $this->actingAs($siteUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('Maple Rise')
        ->assertSee('Acme Homes')
        ->assertSee('Jordan Site')
        ->assertSee('2')
        ->assertSee('Plot 101')
        ->assertSee('Windows')
        ->assertSee('Called Off — Awaiting Date')
        ->assertSee('Plot 102')
        ->assertSee('Cavity Closers')
        ->assertSee('Date Agreed')
        ->assertSee('24 Aug 2026')
        ->assertDontSee('Private office reason')
        ->assertDontSee('Hidden Plot')
        ->assertDontSee('Delete');
});

it('shows no projected plots and no requests empty states', function (): void {
    $user = dashboardUser(PortalRoleIdentifier::SiteManager);
    $site = dashboardAssignedSite($user, ['name' => 'Quiet Site']);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('No projected plots')
        ->assertSee('Source data not yet synchronised');
});

it('routes office staff to the review requests queue', function (): void {
    $user = dashboardUser(PortalRoleIdentifier::FensterOfficeStaff);
    dashboardAssignedSite($user, ['name' => 'Office Scope']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/portal/review-requests');

    $this->actingAs($user)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertSee('Office Scope')
        ->assertSee('No requests match these filters');
});
