<?php

use App\Enums\AdministrativeAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\AdministrativeAudit;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\DemoPurgeImpact;
use App\Services\PermanentDeletionImpact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('keeps normal delete blocked but purges an explicitly certified demo binding and site', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $sibling = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $external = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $external->assignedSites()->attach([$site->id, $sibling->id]);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $bindingId = demoBinding($customer, $site, $office);

    $normal = app(PermanentDeletionImpact::class)->site($site);
    expect($normal['blockers'])->toHaveKey('wald_binding_versions');
    expect(fn () => DB::table('wald_binding_versions')->where('binding_id', $bindingId)->delete())
        ->toThrow(QueryException::class);
    $impact = app(DemoPurgeImpact::class)->site($site);
    $this->actingAs($office)->get(route('office.workspace.sites.demo-purge-preview', [$customer, $site]))
        ->assertOk()->assertSee('Wald Binding Versions')->assertSee('Do not use this action for genuine customer records');
    $this->post(route('office.workspace.sites.demo-purge', [$customer, $site]), demoConfirm($impact))
        ->assertRedirect(route('office.workspace.customers.show', $customer));

    expect(Site::query()->whereKey($site->id)->exists())->toBeFalse()
        ->and(ProjectedPlot::query()->whereKey($plot->id)->exists())->toBeFalse()
        ->and(DB::table('wald_source_bindings')->where('id', $bindingId)->exists())->toBeFalse()
        ->and(DB::table('wald_binding_versions')->where('site_id', $site->id)->exists())->toBeFalse()
        ->and(DB::table('site_user_assignments')->where('site_id', $site->id)->exists())->toBeFalse()
        ->and(DB::table('site_user_assignments')->where('site_id', $sibling->id)->where('user_id', $external->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($external->id)->exists())->toBeTrue()
        ->and(DB::table('demo_purge_gate')->count())->toBe(0)
        ->and(AdministrativeAudit::query()->where('entity_uuid', $site->uuid)->where('action', AdministrativeAction::DemoTestPurged)->exists())->toBeTrue();
});

it('requires certification and PURGE and rejects a changed impact', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $impact = app(DemoPurgeImpact::class)->site($site);
    $route = route('office.workspace.sites.demo-purge', [$customer, $site]);
    $this->actingAs($office)->post($route, ['fingerprint' => $impact['fingerprint'], 'confirmation' => 'PURGE'])
        ->assertSessionHasErrors('certified_demo');
    $this->post($route, ['fingerprint' => $impact['fingerprint'], 'confirmation' => 'DELETE', 'certified_demo' => 1])
        ->assertSessionHasErrors('confirmation');
    ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $this->post($route, demoConfirm($impact))->assertSessionHasErrors('confirmation');
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
});

it('discloses and blocks Portal requests, history and notifications', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id]);
    CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id]);
    DB::table('portal_notifications')->insert([
        'uuid' => (string) Str::uuid(), 'notifiable_user_id' => $office->id, 'type' => 'demo',
        'event_key' => (string) Str::uuid(), 'request_uuid' => (string) Str::uuid(),
        'site_uuid' => $site->uuid, 'site_name' => $site->name, 'plot_reference' => $plot->plot_reference,
        'service_identifier' => 'windows', 'requested_date' => '2026-09-23', 'current_status' => 'demo',
        'route_name' => 'office.workspace.sites.show', 'route_parameters' => '{}',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $impact = app(DemoPurgeImpact::class)->site($site);
    expect($impact['counts']['call_off_requests'])->toBe(1)
        ->and($impact['counts']['call_off_batches'])->toBe(1)
        ->and($impact['counts']['portal_notifications'])->toBe(1)
        ->and($impact['blockers'])->toHaveKeys(['customer_requests', 'call_off_batches', 'portal_notifications']);
    $this->actingAs($office)->get(route('office.workspace.sites.demo-purge-preview', [$customer, $site]))
        ->assertOk()->assertSee('Call Off Requests')->assertSee('Portal Notifications')->assertSee('Cannot purge yet');
    $this->post(route('office.workspace.sites.demo-purge', [$customer, $site]), demoConfirm($impact))
        ->assertSessionHasErrors('confirmation');
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
});

it('purges one committed and one uncommitted demo unit while retaining a shared master and sibling unit', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $demo = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $sibling = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $upload = demoUpload($office);
    $demoUnit = demoWaldUnit($customer, $demo, $office, $upload, 'TEST-A', true);
    $draftUpload = demoUpload($office);
    $draftUnit = demoWaldUnit($customer, $demo, $office, $draftUpload, 'TEST-DRAFT', false);
    $otherUnit = demoWaldUnit($customer, $sibling, $office, $upload, 'TEST-B', true);
    $impact = app(DemoPurgeImpact::class)->site($demo);

    expect($impact['counts']['wald_import_receipts'])->toBe(1)
        ->and($impact['counts']['wald_import_previews'])->toBe(2)
        ->and($impact['counts']['wald_source_rows'])->toBe(2)
        ->and($impact['retained_uploads'])->toBe(2);
    $this->actingAs($office)->post(route('office.workspace.sites.demo-purge', [$customer, $demo]), demoConfirm($impact))
        ->assertRedirect(route('office.workspace.customers.show', $customer));

    expect(DB::table('wald_pilot_uploads')->where('id', $upload['id'])->exists())->toBeTrue()
        ->and(DB::table('wald_pilot_uploads')->where('id', $draftUpload['id'])->exists())->toBeTrue()
        ->and(DB::table('wald_import_runs')->where('id', $demoUnit['run'])->exists())->toBeFalse()
        ->and(DB::table('wald_import_runs')->where('id', $draftUnit['run'])->exists())->toBeFalse()
        ->and(DB::table('wald_import_receipts')->where('run_id', $demoUnit['run'])->exists())->toBeFalse()
        ->and(DB::table('wald_pilot_selections')->where('id', $demoUnit['selection'])->exists())->toBeFalse()
        ->and(DB::table('wald_pilot_selections')->where('id', $draftUnit['selection'])->exists())->toBeFalse()
        ->and(DB::table('wald_source_rows')->where('site_id', $demo->id)->exists())->toBeFalse()
        ->and(DB::table('wald_source_visits')->where('site_id', $demo->id)->exists())->toBeFalse()
        ->and(DB::table('wald_import_runs')->where('id', $otherUnit['run'])->exists())->toBeTrue()
        ->and(DB::table('wald_import_receipts')->where('run_id', $otherUnit['run'])->exists())->toBeTrue()
        ->and(DB::table('wald_pilot_selections')->where('id', $otherUnit['selection'])->exists())->toBeTrue()
        ->and(Site::query()->whereKey($sibling->id)->exists())->toBeTrue()
        ->and(DB::table('projected_plots as plots')->leftJoin('sites', 'sites.id', '=', 'plots.site_id')->whereNull('sites.id')->count())->toBe(0)
        ->and(DB::table('wald_import_runs as runs')->leftJoin('sites', 'sites.id', '=', 'runs.site_id')->whereNull('sites.id')->count())->toBe(0)
        ->and(DB::table('wald_pilot_selections as selections')->leftJoin('sites', 'sites.id', '=', 'selections.site_id')->whereNull('sites.id')->count())->toBe(0);

    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->post(route('office.workspace.pilot-import.selections.commit', [$upload['uuid'], $demoUnit['selection_uuid']]), [
        'preview' => $demoUnit['preview_uuid'], 'hash' => str_repeat('a', 64),
        'confirmation' => 'COMMIT THIS ONE SITE', 'command_uuid' => (string) Str::uuid(),
    ])->assertNotFound();
});

it('blocks an in-flight import and rejects a previously reviewed import state', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $unit = demoWaldUnit($customer, $site, $office, demoUpload($office), 'TEST-ACTIVE', false);
    $impact = app(DemoPurgeImpact::class)->site($site);
    DB::table('wald_import_runs')->where('id', $unit['run'])->update(['state' => 'COMMITTING']);
    $active = app(DemoPurgeImpact::class)->site($site);
    expect($active['blockers'])->toHaveKey('active_import_operations');
    $this->actingAs($office)->post(route('office.workspace.sites.demo-purge', [$customer, $site]), demoConfirm($impact))
        ->assertSessionHasErrors('confirmation');
    $this->get(route('office.workspace.sites.demo-purge-preview', [$customer, $site]))
        ->assertOk()->assertSee('Active Import Operations')->assertSee('Cannot purge yet');
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
});

it('purges a certified customer with multiple demo sites but never its user accounts', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $siteA = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $siteB = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $external = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $blocked = app(DemoPurgeImpact::class)->customer($customer);
    expect($blocked['blockers'])->toHaveKey('customer_users');
    $this->actingAs($office)->get(route('office.workspace.customers.demo-purge-preview', $customer))
        ->assertOk()->assertSee('Customer Users')->assertSee('Cannot purge yet');
    $external->forceFill(['customer_organisation_id' => null, 'is_active' => false])->save();
    $impact = app(DemoPurgeImpact::class)->customer($customer);
    $this->post(route('office.workspace.customers.demo-purge', $customer), demoConfirm($impact))
        ->assertRedirect(route('office.workspace.customers.index'));
    expect(CustomerOrganisation::query()->whereKey($customer->id)->exists())->toBeFalse()
        ->and(Site::query()->whereKey($siteA->id)->exists())->toBeFalse()
        ->and(Site::query()->whereKey($siteB->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($external->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($office->id)->exists())->toBeTrue();
});

it('denies demo purge to every external role and inactive Office', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $actor = User::factory()->role($role)->create(['is_active' => $role !== PortalRoleIdentifier::FensterOfficeStaff]);
    $impact = app(DemoPurgeImpact::class)->site($site);
    $this->actingAs($actor)->get(route('office.workspace.sites.demo-purge-preview', [$customer, $site]))
        ->assertStatus($role === PortalRoleIdentifier::FensterOfficeStaff ? 302 : 403);
    $this->post(route('office.workspace.sites.demo-purge', [$customer, $site]), demoConfirm($impact))
        ->assertStatus($role === PortalRoleIdentifier::FensterOfficeStaff ? 302 : 403);
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
})->with([...PortalRoleIdentifier::siteRoles(), PortalRoleIdentifier::FensterOfficeStaff]);

function demoConfirm(array $impact): array
{
    return ['certified_demo' => 1, 'confirmation' => 'PURGE', 'fingerprint' => $impact['fingerprint']];
}

function demoBinding(CustomerOrganisation $customer, Site $site, User $actor, string $source = 'TEST-1'): int
{
    $bindingId = DB::table('wald_source_bindings')->insertGetId([
        'uuid' => (string) Str::uuid(), 'identity_hash' => hash('sha256', $source),
        'source_namespace' => 'test', 'identity_kind' => 'CUSTOMER_CODE', 'source_identity' => $source,
        'latest_version' => 1, 'active_version' => null, 'revoked_through' => 0, 'epoch' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('wald_binding_versions')->insert([
        'uuid' => (string) Str::uuid(), 'binding_id' => $bindingId, 'version' => 1,
        'customer_organisation_id' => $customer->id, 'site_id' => $site->id,
        'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => 'Demo test',
        'definition_hash' => str_repeat('d', 64), 'created_at' => now(),
    ]);
    DB::table('wald_source_bindings')->where('id', $bindingId)->update(['active_version' => 1]);

    return $bindingId;
}

function demoUpload(User $actor): array
{
    $stream = DB::table('wald_import_streams')->insertGetId([
        'identity_hash' => hash('sha256', (string) Str::uuid()), 'source_namespace' => 'test', 'workbook_family' => 'demo',
    ]);
    $uuid = (string) Str::uuid();
    $key = (string) Str::uuid().'.xlsx';
    $id = DB::table('wald_pilot_uploads')->insertGetId([
        'uuid' => $uuid, 'stream_id' => $stream, 'uploader_id' => $actor->id, 'uploader_name' => $actor->name,
        'storage_key' => $key, 'original_name' => 'synthetic.xlsx', 'format' => 'xlsx', 'mime' => 'application/zip',
        'byte_count' => 100, 'workbook_hash' => str_repeat('f', 64), 'export_date' => '2026-09-22',
        'export_slot' => 'MORNING', 'export_order' => '20260922AM', 'confirmation' => 'Demo fixture',
        'workbook_retain_until' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('id', 'uuid', 'stream', 'key');
}

function demoWaldUnit(CustomerOrganisation $customer, Site $site, User $actor, array $upload, string $code, bool $committed): array
{
    $binding = demoBinding($customer, $site, $actor, $code);
    $selectionUuid = (string) Str::uuid();
    $selection = DB::table('wald_pilot_selections')->insertGetId([
        'uuid' => $selectionUuid, 'pilot_upload_id' => $upload['id'], 'customer_organisation_id' => $customer->id,
        'site_id' => $site->id, 'source_identity_kind' => 'CUSTOMER_CODE', 'source_identity' => $code,
        'source_identity_hash' => hash('sha256', $code), 'binding_id' => $binding, 'binding_version' => 1,
        'binding_definition_hash' => str_repeat('d', 64), 'binding_epoch' => 1,
        'state' => $committed ? 'COMMITTED' : 'READY_TO_COMMIT', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $run = DB::table('wald_import_runs')->insertGetId([
        'uuid' => (string) Str::uuid(), 'stream_id' => $upload['stream'], 'customer_organisation_id' => $customer->id,
        'site_id' => $site->id, 'source_namespace' => 'test', 'workbook_family' => 'demo',
        'uploader_id' => $actor->id, 'uploader_name' => $actor->name, 'storage_key' => $upload['key'],
        'original_name' => 'synthetic.xlsx', 'format' => 'xlsx', 'mime' => 'application/zip', 'byte_count' => 100,
        'workbook_hash' => str_repeat('f', 64), 'export_date' => '2026-09-22', 'export_slot' => 'MORNING',
        'export_order' => '20260922AM', 'confirmation' => 'Demo fixture', 'provenance' => 'OFFICE_DECLARED',
        'coverage' => 'PARTIAL_FILTERED_EXPORT', 'pilot_upload_id' => $upload['id'], 'pilot_selection_id' => $selection,
        'state' => $committed ? 'COMMITTED' : 'READY_TO_COMMIT', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('wald_pilot_selections')->where('id', $selection)->update(['run_id' => $run]);
    $stage = DB::table('wald_import_stages')->insertGetId([
        'uuid' => (string) Str::uuid(), 'run_id' => $run, 'generation' => 1, 'manifest' => '{}',
        'manifest_hash' => str_repeat('a', 64), 'canonical_hash' => str_repeat('b', 64),
        'row_count' => 1, 'blocked_count' => 0, 'created_at' => now(), 'retain_until' => now()->addDays(30),
    ]);
    DB::table('wald_staged_rows')->insert(['stage_id' => $stage, 'ordinal' => 1, 'payload' => '{}', 'payload_hash' => str_repeat('c', 64)]);
    $previewUuid = (string) Str::uuid();
    $preview = DB::table('wald_import_previews')->insertGetId([
        'uuid' => $previewUuid, 'run_id' => $run, 'stage_id' => $stage, 'reviewer_id' => $actor->id,
        'reviewer_name' => $actor->name, 'payload' => '{}', 'payload_hash' => str_repeat('a', 64),
        'created_at' => now(), 'expires_at' => now()->addDay(), 'retain_until' => now()->addDays(30),
    ]);
    if ($committed) {
        $receipt = DB::table('wald_import_receipts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'run_id' => $run, 'preview_id' => $preview, 'stream_id' => $upload['stream'],
            'export_order' => '20260922AM', 'revision' => 1, 'canonical_hash' => str_repeat('b', 64),
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'payload' => '{}', 'payload_hash' => str_repeat('c', 64),
            'unit_scope_key' => hash('sha256', $code), 'created_at' => now(), 'retain_until' => now()->addYears(6),
        ]);
        $attempt = DB::table('wald_commit_attempts')->insertGetId([
            'uuid' => (string) Str::uuid(), 'run_id' => $run, 'preview_id' => $preview,
            'actor_id' => $actor->id, 'command_uuid' => (string) Str::uuid(), 'command_hash' => str_repeat('d', 64),
            'metadata' => '{}', 'metadata_hash' => str_repeat('e', 64), 'created_at' => now(),
        ]);
        DB::table('wald_commit_attempt_outcomes')->insert([
            'attempt_id' => $attempt, 'outcome' => 'COMMITTED', 'category' => 'demo', 'receipt_id' => $receipt, 'created_at' => now(),
        ]);
    }
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $service = ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    $row = DB::table('wald_source_rows')->insertGetId([
        'identity_hash' => hash('sha256', $code.'row'), 'source_namespace' => 'test', 'call_number' => $code,
        'site_id' => $site->id, 'plot_reference' => $plot->plot_reference, 'export_order' => '20260922AM',
        'fact_hash' => str_repeat('a', 64), 'created_at' => now(),
    ]);
    DB::table('wald_source_row_observations')->insert([
        'source_row_id' => $row, 'run_id' => $run, 'version' => 1, 'facts' => '{}', 'provenance' => '{}',
        'fact_hash' => str_repeat('a', 64), 'created_at' => now(), 'retain_until' => now()->addYears(6),
    ]);
    $visit = DB::table('wald_source_visits')->insertGetId([
        'identity_hash' => hash('sha256', $code.'visit'), 'source_namespace' => 'test', 'call_number' => $code,
        'source_row_id' => $row, 'call_type' => 'PC1', 'site_id' => $site->id,
        'plot_reference' => $plot->plot_reference, 'service_identifier' => 'windows',
        'projected_plot_service_id' => $service->id, 'export_order' => '20260922AM',
        'fact_hash' => str_repeat('a', 64), 'created_at' => now(),
    ]);
    DB::table('wald_visit_observations')->insert([
        'visit_id' => $visit, 'run_id' => $run, 'version' => 1, 'facts' => '{}', 'provenance' => '{}',
        'fact_hash' => str_repeat('a', 64), 'created_at' => now(), 'retain_until' => now()->addYears(6),
    ]);

    return ['run' => $run, 'selection' => $selection, 'selection_uuid' => $selectionUuid, 'preview_uuid' => $previewUuid];
}
