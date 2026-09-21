<?php

use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Presenters\CustomerProductPresenter;
use App\Services\CallOffLeadTimeService;
use App\Services\OfficeAdministrationQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{User, Site, ProjectedPlot} */
function customerProductFixture(array $facts, PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager): array
{
    $customer = CustomerOrganisation::factory()->create();
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $customer->id]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create([
        'site_id' => $site->id, 'plot_reference' => 'LABELS-01',
        'external_source' => 'PRIVATE-SOURCE', 'external_identifier' => 'PRIVATE-PLOT-KEY',
    ]);
    foreach (CallOffServiceType::cases() as $service) {
        ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => $service]);
    }
    foreach ($facts as $code => $quantity) {
        ProjectedPlotProduct::create(['projected_plot_id' => $plot->id, 'product_code' => $code, 'quantity' => $quantity]);
    }

    return [$user, $site, $plot];
}

function customerProductPairs(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);
    $pairs = [];
    foreach ($xpath->query('//section[@aria-labelledby="products-heading"]//dl/div') as $row) {
        $pairs[trim($xpath->query('./dt', $row)->item(0)->textContent)] = trim($xpath->query('./dd', $row)->item(0)->textContent);
    }

    return $pairs;
}

it('renders each approved product name and its exact quantity', function (string $code, string $label): void {
    [$user, $site, $plot] = customerProductFixture([$code => '2.000']);
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk();

    expect(customerProductPairs($response->getContent()))->toBe([$label => '2']);
    $this->assertDatabaseHas('projected_plot_products', ['id' => $plot->products->first()->id, 'product_code' => $code, 'quantity' => 2]);
})->with([
    ['VS', 'Vertical Slider'], ['TT', 'Tilt and Turn'], ['BAY', 'Bay Window'],
    ['ALI', 'Aluminium Windows'], ['AOV', 'Automatic Opening Vent Window'], ['FI', 'Fire Window'],
    ['PSU', 'PVC Door Utility'], ['PSG', 'PVC Door Garage'], ['CDF', 'Composite Door Front'],
    ['CDU', 'Composite Door Utility'], ['CDG', 'Composite Door Garage'], ['PSP', 'PVC Sliding Patio'], ['BF', 'Bifold'],
]);

it('keeps multiple exact quantities correctly paired for all three customer roles', function (PortalRoleIdentifier $role): void {
    [$user, $site, $plot] = customerProductFixture(['VS' => '2.000', 'TT' => '3.125', 'BF' => '1.000', 'ALI' => '999999999.999'], $role);
    $before = $plot->products()->orderBy('id')->get()->toArray();
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk()
        ->assertDontSee('PRIVATE-SOURCE')->assertDontSee('PRIVATE-PLOT-KEY')
        ->assertDontSee('CustomerCode')->assertDontSee('Source Binding')->assertDontSee('raw_value');

    expect(customerProductPairs($response->getContent()))->toBe([
        'Aluminium Windows' => '999999999.999', 'Bifold' => '1', 'Tilt and Turn' => '3.125', 'Vertical Slider' => '2',
    ])->and($plot->products()->orderBy('id')->get()->toArray())->toBe($before);

    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $otherPlot = ProjectedPlot::factory()->create(['site_id' => $otherSite->id]);
    $this->get(route('portal.plots.show', $otherPlot))->assertNotFound();
    $this->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site]))->assertForbidden();
})->with(PortalRoleIdentifier::siteRoles());

it('preserves explicit zero as a stored fact while keeping the positive-only display', function (): void {
    [$user, $site, $plot] = customerProductFixture(['VS' => 0, 'TT' => '0.001']);
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk();
    expect(customerProductPairs($response->getContent()))->toBe(['Tilt and Turn' => '0.001']);
    $this->assertDatabaseHas('projected_plot_products', ['projected_plot_id' => $plot->id, 'product_code' => 'VS', 'quantity' => 0]);
    $this->assertDatabaseMissing('projected_plot_products', ['projected_plot_id' => $plot->id, 'product_code' => 'BF']);
});

it('does not create zero rows from missing product facts', function (): void {
    [$user, $site, $plot] = customerProductFixture([]);
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk()
        ->assertSee('No customer-visible product quantities are available for this plot.');
    expect($plot->products()->count())->toBe(0);
});

it('retains unknown legacy facts without inventing names and escapes their codes', function (): void {
    [$user, $site, $plot] = customerProductFixture(['ZZ9' => '7.500', '<script>x</script>' => 1]);
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk()
        ->assertSee('Product (ZZ9)')->assertSee('Product (<script>x</script>)')
        ->assertDontSee('<script>x</script>', false);
    expect(customerProductPairs($response->getContent()))->toBe(['Product (<script>x</script>)' => '1', 'Product (ZZ9)' => '7.5']);
});

it('keeps dictionary-excluded evidence private without deleting stored facts', function (): void {
    $facts = array_fill_keys(['CAS', 'FLU', 'PFD', 'GLS', 'WP', 'MISC'], 1);
    [$user, $site, $plot] = customerProductFixture($facts);
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk()
        ->assertSee('No customer-visible product quantities are available for this plot.');
    expect(customerProductPairs($response->getContent()))->toBe([])
        ->and($plot->products()->count())->toBe(6);
});

it('uses the same labels on call-off check and confirmation without changing canonical review facts', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00:00'));
    [$user, $site, $plot] = customerProductFixture(['VS' => 2, 'TT' => '3.500', 'BF' => 1, 'CAS' => 8]);
    $payload = ['plots' => [$plot->uuid], 'service_dates' => ['windows' => '2026-11-02'], 'customer_response' => 'Local test'];
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    foreach (['portal.call-offs.matrix', 'portal.call-offs.review'] as $route) {
        $response = $this->post(route($route), $payload)->assertOk()
            ->assertSee('Vertical Slider × 2')->assertSee('Tilt and Turn × 3.5')->assertSee('Bifold × 1')
            ->assertDontSee('CAS ×')->assertDontSee('VS ×')->assertDontSee('PRIVATE-PLOT-KEY');
        $rows = $route === 'portal.call-offs.matrix' ? $response->viewData('rows') : $response->viewData('review')['rows'];
        expect(collect($rows[0]['products'])->firstWhere('code', 'VS')['quantity'])->toBe('2.000')
            ->and(collect($rows[0]['products'])->firstWhere('code', 'CAS')['quantity'])->toBe('8.000')
            ->and($rows[0]['normal_earliest_date'])->toBe('2026-10-26');
    }
    $this->assertDatabaseCount('call_off_requests', 0);
    $this->travelBack();
});

it('preserves the positive-BF five-week and zero-BF four-week lead-time rules', function (string $quantity, string $expected): void {
    [$user, $site, $plot] = customerProductFixture(['BF' => $quantity]);
    $service = $plot->services()->where('service_identifier', 'windows')->firstOrFail();
    $from = CarbonImmutable::parse('2026-09-21');
    $before = app(CallOffLeadTimeService::class)->earliestNormalDate($service, $from);
    $display = app(CustomerProductPresenter::class)->present($plot->products);
    expect($before->toDateString())->toBe($expected)
        ->and(app(CallOffLeadTimeService::class)->earliestNormalDate($service->fresh(), $from)->toDateString())->toBe($expected)
        ->and($display->all())->toBe($quantity === '0.000' ? [] : [['label' => 'Bifold', 'quantity' => '1']]);
})->with([['1.000', '2026-10-26'], ['0.000', '2026-10-19']]);

it('preserves Office totals and private source identity while changing only customer labels', function (): void {
    [$user, $site, $plot] = customerProductFixture(['VS' => '2.500', 'TT' => 4, 'PSU' => 2, 'BF' => 1, 'CAS' => 8]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $items = app(OfficeAdministrationQueryService::class)->plots($office, $site->customerOrganisation, $site, null)->items();
    expect($items[0]['product_totals'])->toBe(['windows' => '6.500', 'doors' => '3.000', 'bifold' => '1.000'])
        ->and($items[0]['source_identity'])->toBe(['source' => 'PRIVATE-SOURCE', 'identifier' => 'PRIVATE-PLOT-KEY']);
    $this->actingAs($office)->get(route('office.workspace.sites.show', [$site->customerOrganisation, $site, 'section' => 'plots']))
        ->assertOk()->assertSee('Windows 6.5')->assertSee('Doors 3')->assertSee('Bifold 1')->assertSee('PRIVATE-PLOT-KEY');
});
