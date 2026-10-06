<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\BindingIntegrityAudit;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
});

afterEach(function (): void {
    foreach (DB::table('wald_pilot_uploads')->pluck('storage_key') as $key) {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($key);
    }
});

function robustnessOffice(): User
{
    return User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
}

function robustnessBinding(User $office, Site $site, string $code): array
{
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $service = new SourceBindingService;
    $draft = $service->draft($office, $scope, 'CUSTOMER_CODE', $code, 'Synthetic exact binding.', (string) Str::uuid());

    return $service->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Synthetic reviewed binding.', (string) Str::uuid());
}

function robustnessSource(User $office, string $code, string $customer, string $site): array
{
    return (new PilotImportWorkflow)->upload($office, UploadedFile::fake()->createWithContent('private.csv',
        "CustomerNo,Call No.,Site Name,Plot Ref,Call Type,VS\n{$code},99001,Description,{$customer} - {$site} - Plot 1,PC1,2\n"),
        new ExportOrder('2099-10-06', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
}

it('audits exact customer and site evidence without writing any records', function (): void {
    $office = robustnessOffice();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Exact Customer', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Exact Site', 'is_active' => true]);
    robustnessBinding($office, $site, 'AUDIT1');
    robustnessSource($office, 'AUDIT1', 'Exact Customer', 'Exact Site');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $report = (new BindingIntegrityAudit)->run();
    expect($report['healthy'])->toBe(1)->and($report['blocking'])->toBe(0)
        ->and($report['items'][0]['category'])->toBe('HEALTHY')
        ->and(count($queries))->toBeLessThan(16);
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query)))->toStartWith('select');
    }
    $response = $this->actingAs($office)->get(route('office.workspace.settings.wald.integrity'))->assertOk()->assertSee('Healthy bindings')->assertSee('AUDIT1');
    if (getenv('WALD_ROBUSTNESS_RENDER') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing/wald-robustness-ui'));
        File::put(storage_path('framework/testing/wald-robustness-ui/integrity.html'), $response->getContent());
    }
    $this->artisan('wald:integrity-check')->assertExitCode(0);
});

it('never treats the same site name under a different customer as a match', function (): void {
    $office = robustnessOffice();
    $a = CustomerOrganisation::factory()->create(['name' => 'Customer A', 'is_active' => true]);
    $b = CustomerOrganisation::factory()->create(['name' => 'Customer B', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $a->id, 'name' => 'Barne Barton', 'is_active' => true]);
    Site::factory()->create(['customer_organisation_id' => $b->id, 'name' => 'Barne Barton', 'is_active' => true]);
    robustnessBinding($office, $site, 'AUDIT2');
    robustnessSource($office, 'AUDIT2', 'Customer B', 'Barne Barton');
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('CUSTOMER_MISMATCH');
    $this->artisan('wald:integrity-check')->assertExitCode(1);
});

it('reports site mismatch and conflicting source hierarchy as blockers', function (): void {
    $office = robustnessOffice();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Audit Customer', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Target Site', 'is_active' => true]);
    robustnessBinding($office, $site, 'AUDIT3');
    $pilot = robustnessSource($office, 'AUDIT3', 'Audit Customer', 'Other Site');
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('SITE_MISMATCH');
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    $manifest = json_decode($upload->source_manifest, true);
    $manifest['sources'][0]['hierarchy']['conflicting_rows'] = 1;
    DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
        'source_manifest' => Canonical::json($manifest),
        'source_manifest_hash' => Canonical::hash($manifest),
    ]);
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('SOURCE_HIERARCHY_CONFLICT');
});

it('reports inactive targets and missing or stale evidence without guessing', function (): void {
    $office = robustnessOffice();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Stale Customer', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Stale Site', 'is_active' => true]);
    robustnessBinding($office, $site, 'AUDIT4');
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('UNVERIFIABLE');
    $pilot = robustnessSource($office, 'AUDIT4', 'Stale Customer', 'Stale Site');
    DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->update(['source_manifest_hash' => str_repeat('0', 64)]);
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('STALE_SOURCE_EVIDENCE');
    DB::table('sites')->where('id', $site->id)->update(['is_active' => false]);
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('MISSING_TARGET');
});

it('denies external inactive and preview users and remains available to Office when imports are off', function (): void {
    $office = robustnessOffice();
    config(['wald_import.pilot_available' => false]);
    $this->actingAs($office)->get(route('office.workspace.settings.wald.integrity'))->assertOk();
    foreach (['site_manager', 'assistant_site_manager', 'finishing_foreman'] as $role) {
        $external = User::factory()->create(['is_active' => true, 'portal_role_id' => PortalRole::query()->where('identifier', $role)->value('id')]);
        $this->actingAs($external)->get(route('office.workspace.settings.wald.integrity'))->assertForbidden();
    }
    $preview = robustnessOffice();
    $preview->update(['is_preview_user' => true]);
    $this->actingAs($preview)->get(route('office.workspace.settings.wald.integrity'))->assertForbidden();
    $office->update(['is_active' => false]);
    $this->actingAs($office)->get(route('office.workspace.settings.wald.integrity'))->assertRedirect(route('login'));
});
