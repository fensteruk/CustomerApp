<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\PlotOverviewQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sidebarSiteUser(?CustomerOrganisation $organisation = null): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()->role(PortalRoleIdentifier::SiteManager)->create([
        'customer_organisation_id' => $organisation->id,
    ]);
}

function sidebarAssignedSite(User $user, string $name = 'Willow Park'): Site
{
    $site = Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
        'name' => $name,
    ]);
    $user->assignedSites()->attach($site);

    return $site;
}

function sidebarPlot(Site $site, string $reference): ProjectedPlot
{
    $plot = ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'plot_reference' => $reference,
    ]);

    foreach (CallOffServiceType::cases() as $service) {
        ProjectedPlotService::query()->create([
            'projected_plot_id' => $plot->id,
            'service_identifier' => $service,
        ]);
    }

    return $plot;
}

function sidebarActiveRequest(User $user, Site $site, ProjectedPlot $plot): CallOffRequest
{
    $service = $plot->services()
        ->where('service_identifier', CallOffServiceType::Windows->value)
        ->firstOrFail();
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $user->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => now()->addMonth(),
    ]);

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => now()->addMonth(),
        'status' => CallOffRequestStatus::AwaitingFenster,
    ]);
}

it('renders the site workspace in a persistent desktop sidebar with assigned-site and plot filters', function (): void {
    $user = sidebarSiteUser();
    $site = sidebarAssignedSite($user);
    $otherAssignedSite = sidebarAssignedSite($user, 'Oak View');
    $unassignedSite = Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
        'name' => 'Hidden Development',
    ]);
    sidebarPlot($site, 'Plot 101');

    $response = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard');

    $response->assertOk()
        ->assertSee('data-portal-sidebar', false)
        ->assertSee('w-[14.5rem]', false)
        ->assertSee('xl:pl-[14.5rem]', false)
        ->assertSee('flex h-full min-h-0 flex-col', false)
        ->assertSee('min-h-16 shrink-0', false)
        ->assertSee('min-h-0 flex-1 overflow-x-hidden overflow-y-auto', false)
        ->assertSee('shrink-0 border-t', false)
        ->assertSee('Portal navigation and filters')
        ->assertSee('Plots &amp; Call-Offs', false)
        ->assertSee('Notifications')
        ->assertSee('Willow Park')
        ->assertSee('Oak View')
        ->assertDontSee($unassignedSite->name)
        ->assertSeeInOrder([
            'Nothing Called Off',
            'Call-Offs In Progress',
            'Dates Agreed',
            'Partially Completed',
            'Fully Completed',
        ])
        ->assertSeeInOrder(['Cavity Closers', 'Windows', 'Snagging', 'CML'])
        ->assertSee('Advanced filters')
        ->assertSee('Show Completed')
        ->assertSee('sw-grid', false)
        ->assertSee('Open plot')
        ->assertSee('Plot 101');

    expect($otherAssignedSite->id)->not->toBe($site->id);
});

it('filters by the established overall plot statuses without changing the status definitions', function (): void {
    $user = sidebarSiteUser();
    $site = sidebarAssignedSite($user);
    $nothing = sidebarPlot($site, 'Plot Status Nothing');
    $progress = sidebarPlot($site, 'Plot Status Progress');
    $fullyCompleted = sidebarPlot($site, 'Plot Status Complete');

    sidebarActiveRequest($user, $site, $progress);

    $fullyCompleted->services()->update(['source_completion_observed_at' => now()]);

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);

    $progressItems = app(PlotOverviewQueryService::class)
        ->paginate($site, ['overall_status' => ['call_offs_in_progress']])
        ->pluck('plot.plot_reference');

    expect($progressItems)->toContain($progress->plot_reference)
        ->not->toContain($nothing->plot_reference)
        ->not->toContain($fullyCompleted->plot_reference);

    $this->get('/portal/site-dashboard?overall_status%5B0%5D=call_offs_in_progress')
        ->assertOk()
        ->assertSee('1 filter active');

    $completedItems = app(PlotOverviewQueryService::class)
        ->paginate($site, ['overall_status' => ['fully_completed']])
        ->pluck('plot.plot_reference');

    expect($completedItems)->toContain($fullyCompleted->plot_reference)
        ->not->toContain($progress->plot_reference);

    $this->get('/portal/site-dashboard?overall_status%5B0%5D=fully_completed')
        ->assertOk()
        ->assertSee($fullyCompleted->plot_reference);
});

it('keeps existing service filters shareable through plot pagination', function (): void {
    $user = sidebarSiteUser();
    $site = sidebarAssignedSite($user);

    foreach (range(1, 16) as $number) {
        $plot = sidebarPlot($site, sprintf('Sidebar Plot %02d', $number));
        sidebarActiveRequest($user, $site, $plot);
    }

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard?plot=Sidebar&service=windows&status=awaiting_date&overall_status%5B0%5D=call_offs_in_progress')
        ->assertOk()
        ->assertSee('page=2')
        ->assertSee('plot=Sidebar')
        ->assertSee('service=windows')
        ->assertSee('status=awaiting_date')
        ->assertSee('overall_status%5B0%5D=call_offs_in_progress', false);
});

it('rejects malformed or unsupported sidebar filter query values', function (array $query, string $errorKey): void {
    $user = sidebarSiteUser();
    $site = sidebarAssignedSite($user);
    sidebarPlot($site, 'Validation Plot');

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from('/portal/site-dashboard')
        ->get('/portal/site-dashboard?'.http_build_query($query))
        ->assertRedirect('/portal/site-dashboard')
        ->assertSessionHasErrors($errorKey);
})->with([
    'unknown overall status' => [['overall_status' => ['not-a-real-status']], 'overall_status.0'],
    'scalar overall status' => [['overall_status' => 'nothing_called_off'], 'overall_status'],
    'too many overall statuses' => [['overall_status' => array_fill(0, 6, 'nothing_called_off')], 'overall_status'],
    'unknown service' => [['service' => 'doors'], 'service'],
    'unknown service status' => [['status' => 'internal-status'], 'status'],
    'invalid completed flag' => [['show_completed' => 'sometimes'], 'show_completed'],
]);

it('renders an Office-only sidebar and keeps review filters in that workspace', function (): void {
    $officeUser = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);
    Site::factory()->create(['name' => 'Global Review Site']);

    $this->actingAs($officeUser)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertSee('data-portal-sidebar', false)
        ->assertSee('Review Requests')
        ->assertSee('Review filters')
        ->assertSee('Global Review Site')
        ->assertSee('Notifications')
        ->assertDontSee('Plots &amp; Call-Offs', false)
        ->assertDontSee('Trash');
});

it('provides an accessible tablet and mobile drawer with focus containment', function (): void {
    $user = sidebarSiteUser();
    $site = sidebarAssignedSite($user);
    sidebarPlot($site, 'Drawer Plot');

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('aria-controls="portal-sidebar"', false)
        ->assertSee(':aria-modal="desktop ? null : \'true\'"', false)
        ->assertSee(':inert="!desktop && !sidebarOpen"', false)
        ->assertSee('hidden rounded min-[360px]:block xl:hidden', false)
        ->assertSee('@click.outside="close(false)"', false)
        ->assertSee('Filters');

    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain("window.matchMedia('(min-width: 1280px)')")
        ->toContain('handleSidebarKeydown(event)')
        ->toContain("event.key !== 'Tab'")
        ->toContain("document.querySelector('[data-sidebar-trigger]')?.focus()")
        ->toContain('close(returnFocus = true)')
        ->toContain('if (returnFocus)')
        ->toContain('if (!this.open) return;');
});

it('keeps unauthenticated role preview outside the authenticated application sidebar', function (): void {
    $this->get('/development/role-preview')
        ->assertOk()
        ->assertSee('Development preview')
        ->assertDontSee('data-portal-sidebar', false);
});

it('contains long unbroken plot references within responsive site cards', function (): void {
    $user = sidebarSiteUser();
    $site = sidebarAssignedSite($user);
    $reference = 'PLOT-'.str_repeat('UNBROKEN', 20);
    sidebarPlot($site, $reference);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee($reference)
        ->assertSee('min-w-0 [overflow-wrap:anywhere]', false)
        ->assertSee('sw-plot-title', false);
});
