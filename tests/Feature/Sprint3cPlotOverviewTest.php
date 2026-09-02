<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PlotOverallStatus;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\PlotOverviewQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function sprint3cUser(PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager, ?CustomerOrganisation $organisation = null): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
}

function sprint3cSite(User $user): Site
{
    $site = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $user->assignedSites()->attach($site);

    return $site;
}

function sprint3cPlot(Site $site, string $reference = 'Plot 301'): ProjectedPlot
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference]);

    foreach (CallOffServiceType::cases() as $service) {
        ProjectedPlotService::query()->create([
            'projected_plot_id' => $plot->id,
            'service_identifier' => $service,
        ]);
    }

    return $plot->fresh();
}

function sprint3cService(ProjectedPlot $plot, CallOffServiceType $type): ProjectedPlotService
{
    return ProjectedPlotService::query()
        ->where('projected_plot_id', $plot->id)
        ->where('service_identifier', $type->value)
        ->firstOrFail();
}

function sprint3cRequest(ProjectedPlot $plot, CallOffServiceType $service, CallOffRequestStatus $status, ?string $date = '2026-10-20'): CallOffRequest
{
    $user = $plot->site->assignedUsers()->firstOrFail();
    $batch = CallOffBatch::factory()->create([
        'site_id' => $plot->site_id,
        'submitted_by_user_id' => $user->id,
        'service_identifier' => $service,
        'requested_date' => $date,
    ]);

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => sprint3cService($plot, $service)->id,
        'service_identifier' => $service,
        'requested_date' => $date,
        'agreed_date' => in_array($status, [CallOffRequestStatus::Approved, CallOffRequestStatus::DateAgreed], true) ? $date : null,
        'status' => $status,
    ]);
}

function sprint3cOverview(Site $site, array $filters = []): array
{
    return app(PlotOverviewQueryService::class)->paginate($site, $filters)->items();
}

it('renders one plot overview with the exact four service order and customer-facing states', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot States');
    sprint3cRequest($plot, CallOffServiceType::Windows, CallOffRequestStatus::Submitted);
    sprint3cRequest($plot, CallOffServiceType::Snagging, CallOffRequestStatus::Approved, '2026-10-23');
    sprint3cService($plot, CallOffServiceType::Cml)->update(['source_completed_at' => '2026-10-25']);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Cavity Closers', 'Windows', 'Snagging', 'CML'])
        ->assertSee('Not Called Off')
        ->assertSee('Called Off — Awaiting Date')
        ->assertSee('Date Agreed')
        ->assertSee('23 Oct 2026')
        ->assertSee('Completed')
        ->assertSee('25 Oct 2026')
        ->assertSee('Partially Completed')
        ->assertDontSee('Approved');
});

it('uses source completion over legacy Date Agreed and removes completion after a source reversal', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot Reversal');
    sprint3cRequest($plot, CallOffServiceType::Windows, CallOffRequestStatus::Approved, '2026-10-18');
    $windows = sprint3cService($plot, CallOffServiceType::Windows);
    $windows->update(['source_completed_at' => '2026-10-22']);

    expect(sprint3cOverview($site)[0]->services[CallOffServiceType::Windows->value]->state->value)->toBe('completed');

    $windows->update(['source_completed_at' => null, 'source_completion_observed_at' => null]);

    expect(sprint3cOverview($site)[0]->services[CallOffServiceType::Windows->value]->state->value)->toBe('date_agreed');
});

it('shows completion without inventing a completion date', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot Completion Without Date');
    sprint3cService($plot, CallOffServiceType::CavityClosers)->update(['source_completion_observed_at' => now()]);

    $service = sprint3cOverview($site)[0]->services[CallOffServiceType::CavityClosers->value];

    expect($service->state->value)->toBe('completed')
        ->and($service->date)->toBeNull();
});

it('calculates every overall status centrally', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);

    $nothing = sprint3cPlot($site, 'Plot Nothing');
    $inProgress = sprint3cPlot($site, 'Plot In Progress');
    sprint3cRequest($inProgress, CallOffServiceType::Windows, CallOffRequestStatus::Submitted);
    $agreed = sprint3cPlot($site, 'Plot Agreed');
    sprint3cRequest($agreed, CallOffServiceType::Windows, CallOffRequestStatus::Approved);
    $partial = sprint3cPlot($site, 'Plot Partial');
    sprint3cService($partial, CallOffServiceType::CavityClosers)->update(['source_completion_observed_at' => now()]);
    sprint3cRequest($partial, CallOffServiceType::Windows, CallOffRequestStatus::Submitted);
    $full = sprint3cPlot($site, 'Plot Full');
    foreach (CallOffServiceType::cases() as $service) {
        sprint3cService($full, $service)->update(['source_completion_observed_at' => now()]);
    }

    $states = collect(sprint3cOverview($site))->mapWithKeys(fn ($overview) => [$overview->plot->plot_reference => $overview->overallStatus]);

    expect($states['Plot Nothing'])->toBe(PlotOverallStatus::NothingCalledOff)
        ->and($states['Plot In Progress'])->toBe(PlotOverallStatus::CallOffsInProgress)
        ->and($states['Plot Agreed'])->toBe(PlotOverallStatus::DatesAgreed)
        ->and($states['Plot Partial'])->toBe(PlotOverallStatus::PartiallyCompleted)
        ->and($states->has('Plot Full'))->toBeFalse();

    expect(sprint3cOverview($site, ['show_completed' => true]))->toHaveCount(5);
});

it('hides fully completed plots by default and provides a completed filter state', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot Fully Done');
    foreach (CallOffServiceType::cases() as $service) {
        sprint3cService($plot, $service)->update(['source_completed_at' => '2026-10-01']);
    }

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('All current plots are fully completed')
        ->assertDontSee('Plot Fully Done');

    $this->get('/portal/site-dashboard?show_completed=1')
        ->assertOk()
        ->assertSee('Plot Fully Done')
        ->assertSee('Fully Completed');
});

it('filters plot rows and keeps the customer-facing filters through pagination', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);

    foreach (range(1, 16) as $number) {
        $plot = sprint3cPlot($site, sprintf('Plot Windows %02d', $number));
        sprint3cRequest($plot, CallOffServiceType::Windows, CallOffRequestStatus::Submitted);
    }

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->get('/portal/site-dashboard?plot=Windows&service=windows&status=awaiting_date')
        ->assertOk()
        ->assertSee('Plot Windows 01')
        ->assertSee('page=2')
        ->assertSee('service=windows')
        ->assertSee('status=awaiting_date');
});

it('renders customer-safe Plot Details with only positive product quantities', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot Product Detail');
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'CAS', 'quantity' => 6]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'PFD', 'quantity' => 1]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'BF', 'quantity' => 1]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'VS', 'quantity' => 2]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'ZERO', 'quantity' => 0]);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))
        ->assertOk()
        ->assertSee('Plot Product Detail')
        ->assertSeeInOrder(['Cavity Closers', 'Windows', 'Snagging', 'CML'])
        ->assertSee('Total Windows')
        ->assertSee('Total Doors')
        ->assertDontSee('CAS')
        ->assertDontSee('PFD')
        ->assertDontSee('>BF<', false)
        ->assertDontSee('ZERO')
        ->assertDontSee('projected_plot_id', false)
        ->assertDontSee($plot->external_identifier);
});

it('re-authorises Plot Details UUIDs within the active site and keeps the three external roles equivalent', function (PortalRoleIdentifier $role): void {
    $user = sprint3cUser($role);
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot Assigned');
    $otherAssignedSite = sprint3cSite($user);
    $otherAssignedPlot = sprint3cPlot($otherAssignedSite, 'Plot Other Assigned Site');
    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $otherPlot = sprint3cPlot($otherSite, 'Plot Other Site');
    $otherOrganisationSite = Site::factory()->create(['customer_organisation_id' => CustomerOrganisation::factory()->create()->id]);
    $otherOrganisationPlot = sprint3cPlot($otherOrganisationSite, 'Plot Other Organisation');

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))
        ->assertOk();

    $this->get(route('portal.plots.show', $otherAssignedPlot))->assertNotFound();
    $this->get(route('portal.plots.show', $otherPlot))->assertNotFound();
    $this->get(route('portal.plots.show', $otherOrganisationPlot))->assertNotFound();
})->with(PortalRoleIdentifier::siteRoles());

it('fails malformed Plot Details UUIDs safely and does not admit inactive accounts', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);
    $plot = sprint3cPlot($site, 'Plot Active Account');
    $inactiveUser = User::factory()
        ->inactive()
        ->role(PortalRoleIdentifier::SiteManager)
        ->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $inactiveUser->assignedSites()->attach($site);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/plots/not-a-uuid')
        ->assertNotFound();

    $this->actingAs($inactiveUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

it('does not grow queries per plot-service cell', function (): void {
    $user = sprint3cUser();
    $site = sprint3cSite($user);

    foreach (range(1, 16) as $number) {
        $plot = sprint3cPlot($site, sprintf('Plot Performance %02d', $number));
        sprint3cRequest($plot, CallOffServiceType::Windows, CallOffRequestStatus::Submitted);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $items = sprint3cOverview($site);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($items)->toHaveCount(15)
        ->and($queryCount)->toBeLessThanOrEqual(6);
});
