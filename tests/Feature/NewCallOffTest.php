<?php

use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function newMultiCallOffUser(PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager): array
{
    $organisation = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id, 'name' => 'Maple Rise']);
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);

    return [$user, $site];
}

function newMultiCallOffPlot(Site $site, string $reference, bool $completed = false): ProjectedPlot
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference, 'is_completed' => $completed]);
    ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);

    return $plot;
}

test('the start page is active-site scoped and Office Staff is denied', function (): void {
    [$user, $site] = newMultiCallOffUser();
    newMultiCallOffPlot($site, 'Plot 101');
    newMultiCallOffPlot($site, 'Completed Plot', true);
    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    newMultiCallOffPlot($otherSite, 'Other Site Plot');

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.call-offs.create'))
        ->assertOk()
        ->assertSee('New Call Off')
        ->assertSee('Plot 101')
        ->assertSee('Completed Plot')
        ->assertDontSee('Other Site Plot');

    [$office, $officeSite] = newMultiCallOffUser(PortalRoleIdentifier::FensterOfficeStaff);
    $this->actingAs($office)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $officeSite->id])
        ->get(route('portal.call-offs.create'))
        ->assertForbidden();
});

test('the matrix route validates dates, UUIDs and services before it builds combinations', function (): void {
    [$user, $site] = newMultiCallOffUser();
    $plot = newMultiCallOffPlot($site, 'Plot 201');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);

    $this->from(route('portal.call-offs.create'))->post(route('portal.call-offs.matrix'), [
        'plots' => [$plot->uuid],
        'service_dates' => ['unknown' => 'not-a-date'],
    ])->assertRedirect(route('portal.call-offs.create'))->assertSessionHasErrors('service_dates');

    $this->post(route('portal.call-offs.matrix'), [
        'plots' => [$plot->uuid],
        'service_dates' => [CallOffServiceType::Windows->value => CarbonImmutable::today()->addWeeks(6)->nextWeekday()->toDateString()],
    ])->assertOk()->assertSee('Check combinations')->assertSee('Plot 201');
});

test('the dashboard selected-plots route carries only current active-site UUIDs into the shared start route', function (): void {
    [$user, $site] = newMultiCallOffUser();
    $plot = newMultiCallOffPlot($site, 'Plot 301');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post(route('portal.call-offs.dashboard-selection'), ['plots' => [$plot->uuid]])
        ->assertRedirect(route('portal.call-offs.create', ['plots' => [$plot->uuid]]));
});
