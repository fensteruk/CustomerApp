<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteWorkspaceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function workspaceOffice(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null, 'is_preview_user' => false]);
}

function workspaceSiteUser(Site $site, PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager): User
{
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $site->customer_organisation_id]);
    $user->assignedSites()->attach($site);

    return $user;
}

function workspacePlot(Site $site, string $reference = '101'): ProjectedPlot
{
    return ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference, 'external_identifier' => 'PRIVATE-SOURCE-'.$reference]);
}

function workspaceRequest(ProjectedPlot $plot, CallOffRequestStatus $status, string $service = 'windows', array $extra = []): CallOffRequest
{
    $batch = CallOffBatch::factory()->create(['site_id' => $plot->site_id, 'requested_date' => today()->addDays(20)]);

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id,
        'service_identifier' => $service, 'status' => $status, 'requested_date' => today()->addDays(20),
        ...$extra,
    ]);
}

it('shows the Office site with accurate products and secondary source details without mutations', function (): void {
    $site = Site::factory()->create(['name' => 'Meadow View']);
    $plot = workspacePlot($site);
    foreach (['FLU' => '9', 'PFD' => '2', 'PSU' => '1', 'BF' => '0', 'GLS' => '99'] as $code => $quantity) {
        $plot->products()->create(['product_code' => $code, 'quantity' => $quantity]);
    }
    $office = workspaceOffice();
    $before = [CallOffRequest::count(), $plot->fresh()->getAttributes()];
    $response = $this->actingAs($office)->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site]))
        ->assertOk()->assertSee('Meadow View')->assertSee('Total plots')->assertSee('Plot 101')
        ->assertSee('Windows 9 · Doors 3 · Bifold 0')
        ->assertSee('Show full source reference')->assertSee('PRIVATE-SOURCE-101')
        ->assertSee('Site administration')->assertDontSee('Request a call-off');
    expect($response->viewData('siteSummary')['total'])->toBe(1)
        ->and($response->viewData('cards')[$plot->uuid]['windows'])->toBe('9')
        ->and($response->viewData('cards')[$plot->uuid]['doors'])->toBe('3')
        ->and([CallOffRequest::count(), $plot->fresh()->getAttributes()])->toBe($before);
    $this->get(route('portal.review-requests', ['site' => $site->id]))->assertOk();
});

it('shows all three authorised Site User roles the same safe cards and real routes', function (PortalRoleIdentifier $role): void {
    $site = Site::factory()->create();
    $plot = workspacePlot($site);
    $plot->products()->create(['product_code' => 'VS', 'quantity' => '0']);
    $user = workspaceSiteUser($site, $role);
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard'))->assertOk()
        ->assertSee('Request a call-off')->assertSee('Find a plot')
        ->assertSee(route('portal.plots.show', $plot), false)
        ->assertSee('Call Off Selected')->assertDontSee('PRIVATE-SOURCE-101')
        ->assertDontSee('Site administration')->assertDontSee('Edit Site')->assertDontSee('Purge demo/test data');
    expect($response->viewData('cards')[$plot->uuid]['windows'])->toBe('0')
        ->and($response->viewData('cards')[$plot->uuid]['doors'])->toBe('Not supplied');
})->with([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman]);

it('counts response attention by role and counts plots rather than requests', function (): void {
    $site = Site::factory()->create();
    $officePlot = workspacePlot($site, '101');
    workspaceRequest($officePlot, CallOffRequestStatus::AwaitingFenster, extra: ['is_early_date_exception' => true]);
    workspaceRequest($officePlot, CallOffRequestStatus::Submitted, 'cavity_closers');
    $sitePlot = workspacePlot($site, '102');
    workspaceRequest($sitePlot, CallOffRequestStatus::AwaitingSiteUser);
    $amended = workspacePlot($site, '103');
    $request = workspaceRequest($amended, CallOffRequestStatus::AmendmentOnHold);
    $cycle = $request->dateNegotiations()->create(['purpose' => 'amendment', 'status' => 'open', 'requested_date' => today()->addMonth(), 'opened_at' => now()]);
    $office = workspaceOffice();
    $user = workspaceSiteUser($site);
    $queries = app(SiteWorkspaceQueryService::class);
    expect($queries->workspace($office, $site, [])['siteSummary']['attention'])->toBe(2)
        ->and($queries->workspace($user, $site, [])['siteSummary']['attention'])->toBe(1)
        ->and($queries->workspace($office, $site, [])['cards'][$amended->uuid]['amendment'])->toBeTrue();
    $cycle->proposals()->create(['sequence' => 1, 'proposal_type' => 'fenster_alternative_date', 'status' => 'awaiting_response', 'proposed_date' => today()->addMonth(), 'proposed_by_user_id' => $office->id, 'proposed_at' => now()]);
    expect($queries->workspace($office, $site, [])['siteSummary']['attention'])->toBe(1)
        ->and($queries->workspace($user, $site, [])['siteSummary']['attention'])->toBe(2);
});

it('labels requested and agreed dates truthfully and omits completed and trashed work', function (): void {
    $site = Site::factory()->create();
    $plot = workspacePlot($site);
    workspaceRequest($plot, CallOffRequestStatus::DateAgreed, extra: ['agreed_date' => today()->addDays(2)]);
    workspaceRequest(workspacePlot($site, '102'), CallOffRequestStatus::AwaitingFenster);
    workspaceRequest(workspacePlot($site, '103'), CallOffRequestStatus::AwaitingFenster, extra: ['trashed_at' => now()]);
    $completed = workspacePlot($site, '104');
    $service = $completed->services()->create(['service_identifier' => 'windows', 'source_completion_observed_at' => now()]);
    workspaceRequest($completed, CallOffRequestStatus::DateAgreed, extra: ['agreed_date' => today()->addDay(), 'projected_plot_service_id' => $service->id]);
    $result = app(SiteWorkspaceQueryService::class)->workspace(workspaceOffice(), $site, []);
    expect($result['siteSummary']['upcoming'])->toHaveCount(2)
        ->and($result['siteSummary']['upcoming']->pluck('label')->all())->toBe(['Date Agreed', 'Requested · not agreed'])
        ->and($result['siteSummary']['attention'])->toBe(1);
});

it('keeps empty sites deliberate and lets Office inspect inactive sites without external access', function (): void {
    $site = Site::factory()->create();
    $user = workspaceSiteUser($site);
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard'))->assertOk()->assertSee('No plots yet')->assertSee('None scheduled');
    $site->forceFill(['is_active' => false])->save();
    $this->get(route('portal.site-dashboard'))->assertRedirect(route('sites.select'));
    $this->actingAs(workspaceOffice())->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site]))
        ->assertOk()->assertSee('No plots yet')->assertSee('External access blocked');
});

it('keeps search and status filters scoped and paginated on both site routes', function (): void {
    $site = Site::factory()->create();
    foreach (range(1, 18) as $number) {
        workspaceRequest(workspacePlot($site, sprintf('MATCH-%02d', $number)), CallOffRequestStatus::AwaitingFenster);
    }
    workspacePlot($site, 'NOT-SELECTED');
    workspacePlot(Site::factory()->create(), 'MATCH-PRIVATE');
    $user = workspaceSiteUser($site);
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard', ['plot' => 'MATCH', 'overall_status' => ['call_offs_in_progress']]))
        ->assertOk()->assertViewHas('plots', fn ($plots) => $plots->total() === 18 && $plots->count() === 15)
        ->assertDontSee('MATCH-PRIVATE')->assertDontSee('NOT-SELECTED')->assertSee('plot=MATCH')
        ->assertSeeInOrder(['MATCH-01', 'MATCH-02']);
    $this->get(route('portal.site-dashboard', ['overall_status' => ['']]))->assertOk();
    $this->actingAs(workspaceOffice())->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots', 'search' => 'MATCH', 'overall_status' => ['call_offs_in_progress'], 'page' => 2]))
        ->assertOk()->assertSee('MATCH-16')->assertSee('search=MATCH')
        ->assertViewHas('plots', fn ($plots) => $plots->count() === 3 && ! $plots->pluck('plot.plot_reference')->contains('MATCH-01'));
});

it('keeps the Site Plot search controls collapsed by default with Table and Cards outside', function (): void {
    $site = Site::factory()->create();
    workspacePlot($site);
    $user = workspaceSiteUser($site);

    $external = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard'))
        ->assertOk()
        ->assertSee('Search &amp; Filters', false)
        ->assertSee('Current plots · Reference A–Z')
        ->assertSee('Fully completed plots are hidden by default.')
        ->assertSee('aria-label="Plot view"', false);
    $office = $this->actingAs(workspaceOffice())
        ->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots']))
        ->assertOk()
        ->assertSee('Search &amp; Filters', false)
        ->assertSee('All plots · Reference A–Z')
        ->assertSee('aria-label="Plot view"', false);

    foreach ([$external->getContent(), $office->getContent()] as $content) {
        expect($content)->not->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/')
            ->and(strpos($content, 'aria-label="Plot view"'))->toBeGreaterThan(strpos($content, 'data-plot-filters'));
    }
});

it('summarises and preserves Site Plot search status and sort query values in both disclosures', function (): void {
    $site = Site::factory()->create();
    $plot = workspacePlot($site, 'MATCH-033');
    workspaceRequest($plot, CallOffRequestStatus::AwaitingFenster);
    $user = workspaceSiteUser($site);
    $query = ['overall_status' => ['call_offs_in_progress'], 'sort' => 'plot_desc'];

    $external = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard', ['plot' => 'MATCH'] + $query))
        ->assertOk()
        ->assertSee('3 settings active')
        ->assertSee('name="plot" value="MATCH"', false)
        ->assertSee('value="call_offs_in_progress" selected', false)
        ->assertSee('value="plot_desc" selected', false)
        ->assertSee('Clear filters');

    $office = $this->actingAs(workspaceOffice())
        ->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots', 'search' => 'MATCH'] + $query))
        ->assertOk()
        ->assertSee('3 settings active')
        ->assertSee('name="search" maxlength="100" value="MATCH"', false)
        ->assertSee('value="call_offs_in_progress" selected', false)
        ->assertSee('value="plot_desc" selected', false)
        ->assertSee('Clear');

    expect($external->getContent())->not->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/')
        ->and($office->getContent())->not->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/');
});

it('opens the relevant Site Plot disclosure after validation errors', function (): void {
    $site = Site::factory()->create();
    workspacePlot($site);
    $user = workspaceSiteUser($site);

    $externalRedirect = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from(route('portal.site-dashboard'))
        ->get(route('portal.site-dashboard', ['plot' => 'RETAINED', 'sort' => 'numeric']))
        ->assertRedirect(route('portal.site-dashboard'));
    $external = $this->followRedirects($externalRedirect)
        ->assertOk()
        ->assertSee('The selected sort is invalid.')
        ->assertSee('name="plot" value="RETAINED"', false)
        ->assertSee('1 setting active');
    expect($external->getContent())->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/');

    $officeUrl = route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots']);
    $officeRedirect = $this->actingAs(workspaceOffice())
        ->from($officeUrl)
        ->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots', 'search' => 'RETAINED', 'sort' => 'numeric']))
        ->assertRedirect($officeUrl);
    $office = $this->followRedirects($officeRedirect)
        ->assertOk()
        ->assertSee('The selected sort is invalid.')
        ->assertSee('name="search" maxlength="100" value="RETAINED"', false)
        ->assertSee('1 setting active');
    expect($office->getContent())->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/');
});

it('safely re-renders array-shaped Site Plot search input after validation', function (): void {
    $site = Site::factory()->create();
    workspacePlot($site);
    $user = workspaceSiteUser($site);

    $externalRedirect = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->from(route('portal.site-dashboard'))
        ->get(route('portal.site-dashboard', ['plot' => ['x']]))
        ->assertRedirect(route('portal.site-dashboard'));
    $external = $this->followRedirects($externalRedirect)
        ->assertOk()
        ->assertSee('The plot field must be a string.')
        ->assertSee('name="plot" value=""', false);
    expect($external->getContent())->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/');

    $officeUrl = route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots']);
    $officeRedirect = $this->actingAs(workspaceOffice())
        ->from($officeUrl)
        ->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots', 'search' => ['x']]))
        ->assertRedirect($officeUrl);
    $office = $this->followRedirects($officeRedirect)
        ->assertOk()
        ->assertSee('The search field must be a string.')
        ->assertSee('name="search" maxlength="100" value=""', false);
    expect($office->getContent())->toMatch('/<details[^>]*data-plot-filters[^>]*\bopen\b/');
});

it('renders the shared Table and Cards choices from one page of authorised plot data', function (): void {
    $site = Site::factory()->create(['name' => 'Selector Site']);
    $plot = workspacePlot($site, '033');
    workspaceRequest($plot, CallOffRequestStatus::AwaitingFenster);
    $user = workspaceSiteUser($site);

    $external = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard'))
        ->assertOk()
        ->assertSee('aria-label="Plot view"', false)
        ->assertSee('data-default-view="cards"', false)
        ->assertSee('data-desktop-default-view="table"', false)
        ->assertSee('data-plot-view="table"', false)
        ->assertSee('data-plot-view="cards"', false)
        ->assertSeeInOrder(['Cavity Closers', 'Windows', 'Snagging', 'CML', 'Overall status', 'Actions'])
        ->assertSee('Called Off — Awaiting Date')
        ->assertSee(route('portal.plots.show', $plot), false)
        ->assertSee(route('portal.call-offs.create', ['plots' => [$plot->uuid]]), false)
        ->assertSee('<noscript>', false)
        ->assertDontSee('Bifold')
        ->assertDontSee('PRIVATE-SOURCE-033');

    expect($external->getContent())->toContain('<template x-if="view === \'table\'">')
        ->and($external->getContent())->toContain('<template x-if="view === \'cards\'">')
        ->and($external->getContent())->toContain('try { window.localStorage.setItem(storageKey, next); } catch (error) {}')
        ->and($external->getContent())->toContain('if (isView(stored)) this.view = stored;');

    $office = $this->actingAs(workspaceOffice())
        ->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots']))
        ->assertOk()
        ->assertSee('aria-label="Inspect plot information for Plot 033"', false)
        ->assertSee('Inspect plot information')
        ->assertSee('PRIVATE-SOURCE-033')
        ->assertSee('Inspect source services')
        ->assertSee('No service information supplied.');

    expect(substr_count($office->getContent(), 'name="plots[]"'))->toBe(0);
});

it('defaults to Cards on mobile and Table on desktop for every site size without overriding valid stored choices', function (): void {
    $site = Site::factory()->create();
    workspacePlot($site, 'APT 01');
    $user = workspaceSiteUser($site);

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard', ['plot' => 'does-not-match']))
        ->assertOk()->assertDontSee('data-default-view=', false);

    $small = $this->get(route('portal.site-dashboard'))->assertOk();
    $small->assertSee('data-default-view="cards"', false)
        ->assertSee('data-desktop-default-view="table"', false)
        ->assertSee('data-desktop-query="(min-width: 1024px)"', false);
    expect($small->getContent())->toContain('desktop = Boolean(desktopQuery) && window.matchMedia(desktopQuery).matches;')
        ->and($small->getContent())->toContain("view: desktop && isView(desktopView) ? desktopView : (isView(fallback) ? fallback : 'cards'),")
        ->and($small->getContent())->toContain('if (isView(stored)) this.view = stored;');

    ProjectedPlot::factory()->count(120)->create(['site_id' => $site->id]);
    $this->get(route('portal.site-dashboard'))->assertOk()
        ->assertSee('data-default-view="cards"', false)
        ->assertSee('data-desktop-default-view="table"', false);

    $this->actingAs(workspaceOffice())
        ->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots']))
        ->assertOk()
        ->assertSee('data-default-view="cards"', false)
        ->assertSee('data-desktop-default-view="table"', false);
});

it('sorts plot references as strings in both directions and allowlists the request value', function (): void {
    $site = Site::factory()->create();
    foreach (['033', 'Com 4', 'APT 03'] as $reference) {
        workspacePlot($site, $reference);
    }
    $user = workspaceSiteUser($site);

    $ascending = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard', ['sort' => 'plot_asc']))
        ->assertOk()->assertViewHas('filters', fn (array $filters): bool => $filters['sort'] === 'plot_asc')
        ->viewData('plots')->pluck('plot.plot_reference')->all();
    $descending = $this->get(route('portal.site-dashboard', ['sort' => 'plot_desc']))
        ->assertOk()->assertViewHas('filters', fn (array $filters): bool => $filters['sort'] === 'plot_desc')
        ->viewData('plots')->pluck('plot.plot_reference')->all();

    expect($ascending)->toBe(['033', 'APT 03', 'Com 4'])
        ->and($descending)->toBe(['Com 4', 'APT 03', '033']);
    $this->get(route('portal.site-dashboard', ['sort' => 'numeric']))->assertRedirect();
});

it('denies unassigned cross-customer inactive-user and Office-route access without changing policy', function (): void {
    $site = Site::factory()->create();
    $user = workspaceSiteUser($site);
    $other = Site::factory()->create();
    $unassigned = Site::factory()->create(['customer_organisation_id' => $site->customer_organisation_id]);
    $this->actingAs($user)->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site]))->assertForbidden();
    foreach ([$other, $unassigned] as $denied) {
        $this->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $denied->id])
            ->get(route('portal.site-dashboard'))->assertRedirect(route('sites.select'));
    }
    $user->update(['is_active' => false]);
    $this->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])->get(route('portal.site-dashboard'))->assertRedirect(route('login'));
    $this->actingAs(workspaceOffice())->get(route('office.workspace.sites.show', [$other->customerOrganisation, $site]))->assertNotFound();
});

it('keeps workspace query count bounded as a site grows', function (): void {
    $site = Site::factory()->create();
    $office = workspaceOffice();
    workspaceRequest(workspacePlot($site), CallOffRequestStatus::AwaitingFenster);
    $queries = app(SiteWorkspaceQueryService::class);
    DB::enableQueryLog();
    $queries->workspace($office, $site, []);
    $small = count(DB::getQueryLog());
    DB::disableQueryLog();
    foreach (range(2, 30) as $number) {
        workspaceRequest(workspacePlot($site, (string) $number), CallOffRequestStatus::AwaitingFenster);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = $queries->workspace($office, $site, []);
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($result['plots']->count())->toBe(15)->and($large)->toBeLessThanOrEqual($small + 2);
});
