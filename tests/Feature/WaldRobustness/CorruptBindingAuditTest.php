<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\BindingIntegrityAudit;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Corruption fixtures use an isolated in-memory SQLite database only.');
    }
    if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('in_memory_corruption_fixture_required');
    }
    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    // Simulate historical corruption that real current FKs/immutable triggers normally reject.
    DB::statement('PRAGMA foreign_keys = OFF');
    DB::statement('DROP TRIGGER w5_binding_identity');
    DB::statement('DROP TRIGGER w5_binding_delete');
    DB::statement('DROP TRIGGER w5_immutable_0_update');
    DB::statement('DROP TRIGGER w5_immutable_0_delete');
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->update(['enabled' => true]);
});

function corruptFixture(): array
{
    $office = User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
    $customer = CustomerOrganisation::factory()->create(['is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $service = new SourceBindingService;
    $draft = $service->draft($office, $scope, 'CUSTOMER_CODE', 'CORRUPT', 'Synthetic audit fixture.', (string) Str::uuid());
    $service->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Synthetic audit fixture.', (string) Str::uuid());
    $root = DB::table('wald_source_bindings')->where('uuid', $draft['binding'])->firstOrFail();

    return compact('office', 'customer', 'site', 'scope', 'root');
}

it('finds a missing site and refuses its future binding resolution', function (): void {
    $f = corruptFixture();
    DB::table('sites')->where('id', $f['site']->id)->delete();
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('MISSING_TARGET');
    expect(fn () => (new SourceBindingService)->resolve($f['office'], $f['scope'], 'CUSTOMER_CODE', 'CORRUPT'))
        ->toThrow(AuthorizationException::class);
});

it('finds wrong customer ownership even when names are identical', function (): void {
    $f = corruptFixture();
    $other = CustomerOrganisation::factory()->create(['is_active' => true]);
    DB::table('sites')->where('id', $f['site']->id)->update(['customer_organisation_id' => $other->id]);
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('CUSTOMER_MISMATCH');
});

it('finds missing active versions and orphan history', function (): void {
    $f = corruptFixture();
    DB::table('wald_binding_versions')->where('binding_id', $f['root']->id)->delete();
    expect((new BindingIntegrityAudit)->run()['items'][0]['category'])->toBe('ORPHAN_BINDING_VERSION');
    DB::table('wald_binding_versions')->insert(['uuid' => (string) Str::uuid(), 'binding_id' => 99999, 'version' => 1,
        'customer_organisation_id' => $f['customer']->id, 'site_id' => $f['site']->id,
        'actor_id' => $f['office']->id, 'actor_name' => $f['office']->name, 'reason' => 'Orphan fixture',
        'definition_hash' => str_repeat('0', 64), 'created_at' => now()]);
    expect((new BindingIntegrityAudit)->run()['blocking'])->toBe(2);
});

it('detects duplicate active CustomerCode roots even with different stored hashes', function (): void {
    $f = corruptFixture();
    $root = (array) $f['root'];
    unset($root['id']);
    $root['uuid'] = (string) Str::uuid();
    $root['identity_hash'] = Canonical::hash(['corrupted-duplicate']);
    $id = DB::table('wald_source_bindings')->insertGetId($root);
    $version = (array) DB::table('wald_binding_versions')->where('binding_id', $f['root']->id)->first();
    unset($version['id']);
    $version['uuid'] = (string) Str::uuid();
    $version['binding_id'] = $id;
    DB::table('wald_binding_versions')->insert($version);
    expect((new BindingIntegrityAudit)->run()['categories']['DUPLICATE_ACTIVE_BINDING'])->toBe(2);
});

it('accepts a retained cleared root after purge and cannot resolve the purged target', function (): void {
    $f = corruptFixture();
    DB::table('wald_source_bindings')->where('id', $f['root']->id)->update(['active_version' => null, 'latest_version' => 0]);
    DB::table('wald_binding_versions')->where('binding_id', $f['root']->id)->delete();
    DB::table('sites')->where('id', $f['site']->id)->delete();
    expect((new BindingIntegrityAudit)->run()['blocking'])->toBe(0);
    expect(fn () => (new SourceBindingService)->resolve($f['office'], $f['scope'], 'CUSTOMER_CODE', 'CORRUPT'))
        ->toThrow(AuthorizationException::class);
});

it('detects an orphan historical target behind an otherwise valid active version', function (): void {
    $f = corruptFixture();
    $service = new SourceBindingService;
    $draft = $service->draft($f['office'], $f['scope'], 'CUSTOMER_CODE', 'CORRUPT', 'Second audit version.',
        (string) Str::uuid(), (int) $f['root']->epoch);
    $service->activate($f['office'], $f['scope'], $draft['binding'], $draft['version'], $draft['definition_hash'],
        $draft['epoch'], 'Reviewed second version.', (string) Str::uuid());
    DB::table('wald_binding_versions')->where('binding_id', $f['root']->id)->where('version', 1)->update(['site_id' => 99999]);
    expect((new BindingIntegrityAudit)->run()['categories']['ORPHAN_BINDING_VERSION'])->toBe(1);
});
