<?php

use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Http\Middleware\PreventAuthenticatedResponseCaching;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function expectPrivateNoStore(TestResponse $response): void
{
    $directives = collect(explode(',', (string) $response->headers->get('Cache-Control')))
        ->map(fn (string $directive): string => trim($directive));

    expect($directives)->toContain('no-store')
        ->and($directives)->toContain('private');
}

it('prevents storage of protected Office HTML and JSON responses', function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);

    $html = $this->actingAs($office)
        ->get(route('office.workspace.customers.index'))
        ->assertOk();
    $imports = $this->get(route('office.workspace.imports'))->assertOk();
    $json = $this->getJson(route('portal.office.sites.imports', [$customer, $site]))
        ->assertOk();

    expectPrivateNoStore($html);
    expectPrivateNoStore($imports);
    expectPrivateNoStore($json);
});

it('prevents storage of protected customer site and plot HTML responses', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create([
        'customer_organisation_id' => $customer->id,
    ]);
    $user->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    foreach (CallOffServiceType::cases() as $service) {
        ProjectedPlotService::query()->create([
            'projected_plot_id' => $plot->id,
            'service_identifier' => $service,
        ]);
    }

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $dashboard = $this->get(route('portal.site-dashboard'))->assertOk();
    $details = $this->get(route('portal.plots.show', $plot))->assertOk();

    expectPrivateNoStore($dashboard);
    expectPrivateNoStore($details);
});

it('prevents storage of protected HTML rendered from a POST request', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create([
        'customer_organisation_id' => $customer->id,
    ]);
    $user->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
    ]);

    $response = $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post(route('portal.call-offs.matrix'), [
            'plots' => [$plot->uuid],
            'service_dates' => [
                CallOffServiceType::Windows->value => CarbonImmutable::today()->addWeeks(6)->nextWeekday()->toDateString(),
            ],
        ])
        ->assertOk()
        ->assertSee('Check combinations');

    expectPrivateNoStore($response);
});

it('leaves public entry points outside the protected response policy', function (): void {
    $login = $this->get(route('login'))->assertOk();
    $health = $this->get('/up')->assertOk();

    expect((string) $login->headers->get('Cache-Control'))->not->toContain('no-store')
        ->and((string) $health->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('includes the policy in the Livewire update pipeline', function (): void {
    $route = app('router')->getRoutes()->getByName('default-livewire.update');

    expect($route)->not->toBeNull()
        ->and(app('router')->gatherRouteMiddleware($route))
        ->toContain(PreventAuthenticatedResponseCaching::class);
});

it('keeps health and versioned static resources outside application web middleware', function (): void {
    $healthRoute = app('router')->getRoutes()->match(Request::create('/up', 'GET'));
    $applicationUris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route): string => $route->uri());

    expect(app('router')->gatherRouteMiddleware($healthRoute))
        ->not->toContain(PreventAuthenticatedResponseCaching::class)
        ->and($applicationUris->contains(fn (string $uri): bool => str_starts_with($uri, 'build/')))
        ->toBeFalse();
});

it('invalidates the session on logout and protects the logout response', function (): void {
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create();

    $this->actingAs($user)->withSession(['privacy_marker' => 'sensitive']);
    $sessionId = session()->getId();
    $csrfToken = session()->token();

    $logout = $this->post(route('logout'))->assertRedirect(route('login'));

    expectPrivateNoStore($logout);
    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionId)
        ->and(session()->token())->not->toBe($csrfToken)
        ->and(session()->has('privacy_marker'))->toBeFalse();
    $this->get(route('sites.select'))->assertRedirect(route('login'));
});
