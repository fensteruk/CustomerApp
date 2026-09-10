<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

it('renders every synthetic step with persistent safety labels and no mutation capability', function (): void {
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
        'is_preview_user' => true,
    ]);

    $response = $this->actingAs($office)->get(route('development.import-studio.show'));

    $response->assertOk()
        ->assertSee('DEMO ONLY')
        ->assertSee('SYNTHETIC DATA')
        ->assertSee('NO DATA WILL BE SAVED')
        ->assertSee('precomputed product walkthrough')
        ->assertSee('Upload / Select Example')
        ->assertSee('Latest Export Confirmation')
        ->assertSee('Wald Analysis')
        ->assertSee('Detected Site')
        ->assertSee('Source Binding')
        ->assertSee('Detected Records')
        ->assertSee('Clarifications')
        ->assertSee('Preview Changes')
        ->assertSee('Commit Summary')
        ->assertSee('Commit unavailable in demo')
        ->assertSee('Northstar Homes (fictional)')
        ->assertSee('Precomputed demonstration — the Wald engine has not run')
        ->assertDontSee('type="file"', false)
        ->assertDontSee('multipart/form-data', false);

    $routes = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn ($route, string $name): bool => str_starts_with($name, 'development.import-studio.'));

    expect($routes)->toHaveCount(2);
    $routes->each(fn ($route) => expect($route->methods())->toEqual(['GET', 'HEAD']));
});

it('allows only authenticated active Office roles without turning preview into real admin authority', function (): void {
    $previewOffice = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'is_preview_user' => true,
    ]);
    $persistedOffice = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);

    $this->actingAs($previewOffice)->get(route('development.import-studio.show'))->assertOk();
    $this->actingAs($persistedOffice)->get(route('development.import-studio.show'))->assertOk();
    $this->actingAs($previewOffice)->postJson(route('portal.office.customers.store'), ['name' => 'Forbidden demo write'])
        ->assertForbidden();

    foreach (PortalRoleIdentifier::siteRoles() as $role) {
        $external = User::factory()->role($role)->create();
        $this->actingAs($external)->get(route('development.import-studio.show'))->assertForbidden();
    }

    $inactive = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
        'is_active' => false,
    ]);
    $this->actingAs($inactive)->get(route('development.import-studio.show'))->assertRedirect(route('login'));

    auth()->logout();
    $this->get(route('development.import-studio.show'))->assertRedirect(route('login'));
});

it('uses site context only as a contained launch label and leaves every relevant table unchanged', function (): void {
    $customer = CustomerOrganisation::factory()->create(['name' => 'Local QA Customer']);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Local QA Site']);
    $otherCustomer = CustomerOrganisation::factory()->create();
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);
    $tables = [
        'customer_organisations', 'sites', 'projected_plots', 'projected_plot_services',
        'call_off_batches', 'call_off_requests', 'call_off_status_histories',
        'administrative_audits', 'wald_source_bindings', 'wald_binding_versions',
        'wald_import_runs', 'wald_import_streams', 'wald_import_stages', 'wald_staged_rows',
        'wald_import_previews', 'wald_import_receipts',
    ];
    $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    $this->actingAs($office)->get(route('development.import-studio.site', [$customer, $site]))
        ->assertOk()
        ->assertSee('Opened from Local QA Site')
        ->assertSee('walkthrough still uses only the separate synthetic scenario');

    $this->get(route('development.import-studio.site', [$otherCustomer, $site]))->assertNotFound();
    $after = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    expect($after->all())->toEqual($before->all());
});

it('adds local demo entry points without exposing upload or commit controls in administration', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);

    $this->actingAs($office)->get(route('office.workspace.imports'))
        ->assertOk()
        ->assertSee('New Import')
        ->assertSee('DEMO ONLY')
        ->assertDontSee('type="file"', false);

    $this->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('Import Source Data')
        ->assertSee(route('development.import-studio.site', [$customer, $site]), false);
});

it('fails closed when the runtime demo flag is disabled', function (): void {
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);
    config(['import-demo.enabled' => false]);

    $this->actingAs($office)->get('/development/import-studio')->assertNotFound();
    $this->get(route('office.workspace.imports'))->assertOk()->assertDontSee('New Import');
});

it('does not register demo routes when the application boots as production even if the flag is requested', function (): void {
    $process = new Process([
        PHP_BINARY,
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo Illuminate\Support\Facades\Route::has('development.import-studio.show') ? 'present' : 'absent';
        PHP,
    ], base_path(), [
        ...getenv(),
        'APP_ENV' => 'production',
        'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'IMPORT_STUDIO_DEMO_ENABLED' => 'true',
    ]);
    $process->setTimeout(30)->run();

    expect($process->getExitCode())->toBe(0)
        ->and(trim($process->getOutput()))->toBe('absent');
});
