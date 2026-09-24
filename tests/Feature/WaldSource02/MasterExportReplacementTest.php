<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ApproveMasterHierarchyProposal;
use App\SourceImport\Integration\BackendStore;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\HierarchyClarifications;
use App\SourceImport\Integration\IdenticalPilotImportConflict;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\PilotReplacementConfirmationRequired;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
});

afterEach(function (): void {
    $storage = Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')]);
    foreach (DB::table('wald_pilot_uploads')->pluck('storage_key') as $key) {
        $storage->delete($key);
    }
});

function source02Office(array $attributes = []): User
{
    return User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
        ...$attributes,
    ]);
}

function source02Workbook(array $rows, bool $withCustomerCode = true, string $customerCodeHeader = 'CustomerNo'): UploadedFile
{
    $headers = array_values(array_filter([
        $withCustomerCode ? $customerCodeHeader : null,
        'Call No.',
        'Site Name',
        'Plot Ref',
        'Call Type',
        'Complete',
        'VS',
    ]));
    $lines = [implode(',', $headers)];
    foreach ($rows as $row) {
        $values = array_filter([
            $withCustomerCode ? ($row['code'] ?? '') : null,
            $row['call'],
            $row['site'],
            $row['plot'],
            $row['type'] ?? 'PC1',
            $row['complete'] ?? 'No',
            $row['vs'] ?? '2',
        ], fn (mixed $value, int $index): bool => $withCustomerCode || $index !== 0, ARRAY_FILTER_USE_BOTH);
        $lines[] = implode(',', $values);
    }

    return UploadedFile::fake()->createWithContent('master.csv', implode("\n", $lines)."\n");
}

function source02Upload(
    User $office,
    array $rows,
    string $date,
    string $slot = 'MORNING',
    ?string $predecessor = null,
    ?string $replacementConfirmation = null,
    bool $withCustomerCode = true,
    string $customerCodeHeader = 'CustomerNo',
): array {
    return (new PilotImportWorkflow)->upload(
        $office,
        source02Workbook($rows, $withCustomerCode, $customerCodeHeader),
        new ExportOrder($date, $slot),
        ExportOrder::CONFIRMATION,
        (string) Str::uuid(),
        $predecessor,
        null,
        $replacementConfirmation,
    );
}

function source02Bind(User $office, array $source, Site $site): void
{
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $service = new SourceBindingService;
    $draft = $service->draft($office, $scope, $source['kind'], $source['identity'], 'Exact CustomerCode binding.', (string) Str::uuid());
    $service->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed CustomerCode binding.', (string) Str::uuid());
}

function source02Analyse(User $office, KnowledgeScope $scope, object $run): object
{
    try {
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    } catch (ImportConflict $exception) {
        if ($exception->getMessage() !== 'structural_clarification_required') {
            throw $exception;
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $context = KnowledgeContext::query()->findOrFail($run->context_id);
        foreach ((new KnowledgeQueries)->questions($office, $scope, $context->uuid) as $question) {
            if ($question['state'] !== 'ANSWERED' && $question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                (new AnswerClarification)->handle($office, $scope, $context->uuid, $question['uuid'], $question['sequence'], $question['evidence']['candidates'][0]['id'], 'Reviewed deterministic header.', (string) Str::uuid());
            }
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    }

    return DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
}

function source02Approve(User $office, array $pilot, array $source, string $outcome, ?string $command = null): array
{
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    return app(ApproveMasterHierarchyProposal::class)->handle($office, $pilot['upload'], $source['hash'],
        $upload->source_manifest_hash, (int) $upload->epoch, $outcome,
        $source['resolution']['customer'], $source['resolution']['site'], $command ?? (string) Str::uuid());
}

it('approves a new customer and site atomically then reuses them on repeat import', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2473', 'call' => '9001', 'site' => 'Descriptive', 'plot' => 'Lovell - Barne Barton - Plot 1'],
        ['code' => 'FNA2473', 'call' => '9002', 'site' => 'Descriptive', 'plot' => 'Lovell - Barne Barton - Plot 2'],
    ], '2099-11-01');
    $source = $pilot['sources'][0];
    $command = (string) Str::uuid();
    $result = source02Approve($office, $pilot, $source, 'NEW_CUSTOMER_AND_SITE', $command);
    expect($result['customer_created'])->toBeTrue()
        ->and($result['resulting_resolution'])->toBe('EXACT_EXISTING_BINDING')
        ->and(CustomerOrganisation::query()->where('name', 'Lovell')->count())->toBe(1)
        ->and(Site::query()->where('name', 'Barne Barton')->count())->toBe(1)
        ->and(DB::table('wald_source_bindings')->count())->toBe(1)
        ->and(DB::table('wald_pilot_events')->where('action', 'pilot_hierarchy_creation_approved')->count())->toBe(1)
        ->and(DB::table('site_user_assignments')->count())->toBe(0)
        ->and(DB::table('projected_plots')->count())->toBe(0);
    expect(source02Approve($office, $pilot, $source, 'NEW_CUSTOMER_AND_SITE', $command)['binding_uuid'])
        ->toBe($result['binding_uuid']);
    expect((new PilotImportWorkflow)->summary($office, $pilot['upload'])['sources'][0]['resolution']['state'])
        ->toBe('EXACT_EXISTING_BINDING');
    $later = source02Upload($office, [
        ['code' => 'FNA2473', 'call' => '9003', 'site' => 'Changed description', 'plot' => 'Lovell - Barne Barton - Plot 3'],
    ], '2099-11-02');
    expect($later['sources'][0]['resolution']['state'])->toBe('EXACT_EXISTING_BINDING');
});

it('approves a new site beneath one exact existing customer and refreshes sibling proposals', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'CODE-A', 'call' => '9101', 'site' => 'A', 'plot' => 'Vistry - Site A - Plot 1'],
        ['code' => 'CODE-B', 'call' => '9102', 'site' => 'B', 'plot' => 'Vistry - Site B - Plot 2'],
    ], '2099-11-03');
    $first = collect($pilot['sources'])->firstWhere('identity', 'CODE-A');
    $second = collect($pilot['sources'])->firstWhere('identity', 'CODE-B');
    $result = source02Approve($office, $pilot, $first, 'NEW_CUSTOMER_AND_SITE');
    $updated = (new PilotImportWorkflow)->summary($office, $pilot['upload']);
    expect(collect($updated['sources'])->firstWhere('identity', 'CODE-B')['resolution']['state'])
        ->toBe('EXACT_CUSTOMER_NEW_SITE');
    expect(fn () => source02Approve($office, $pilot, $second, 'NEW_CUSTOMER_AND_SITE'))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    $secondResult = source02Approve($office, $pilot, collect($updated['sources'])->firstWhere('identity', 'CODE-B'), 'EXACT_CUSTOMER_NEW_SITE');
    expect($result['customer_id'])->toBe($secondResult['customer_id'])
        ->and($secondResult['customer_created'])->toBeFalse()
        ->and(CustomerOrganisation::query()->where('name', 'Vistry')->count())->toBe(1)
        ->and(Site::query()->where('customer_organisation_id', $result['customer_id'])->count())->toBe(2)
        ->and(DB::table('wald_source_bindings')->count())->toBe(2);
});

it('rejects stale, conflicting and unauthorised hierarchy approvals without creating records', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'SAFE', 'call' => '9201', 'site' => 'A', 'plot' => 'New Customer - New Site - Plot 1'],
        ['code' => 'CONFLICT', 'call' => '9202', 'site' => 'B', 'plot' => 'Baker - Site A - Plot 2'],
        ['code' => 'CONFLICT', 'call' => '9203', 'site' => 'B', 'plot' => 'Baker Estates LTD - Site A - Plot 3'],
    ], '2099-11-04');
    $safe = collect($pilot['sources'])->firstWhere('identity', 'SAFE');
    $conflict = collect($pilot['sources'])->firstWhere('identity', 'CONFLICT');
    expect(fn () => source02Approve($office, $pilot, $conflict, 'NEW_CUSTOMER_AND_SITE'))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    expect(fn () => app(ApproveMasterHierarchyProposal::class)->handle($office, $pilot['upload'], $safe['hash'],
        str_repeat('0', 64), (int) $upload->epoch, 'NEW_CUSTOMER_AND_SITE', 'New Customer', 'New Site', (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    expect(fn () => app(ApproveMasterHierarchyProposal::class)->handle($office, $pilot['upload'], $safe['hash'],
        $upload->source_manifest_hash, (int) $upload->epoch, 'NEW_CUSTOMER_AND_SITE', 'Different Customer', 'New Site', (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    $siteUser = User::factory()->create(['portal_role_id' => PortalRole::query()->where('identifier', 'site_manager')->value('id')]);
    expect(fn () => source02Approve($siteUser, $pilot, $safe, 'NEW_CUSTOMER_AND_SITE'))
        ->toThrow(AuthorizationException::class);
    expect(CustomerOrganisation::query()->where('name', 'New Customer')->count())->toBe(0)
        ->and(DB::table('wald_source_bindings')->count())->toBe(0);
});

it('rolls back customer or site creation when later approval steps fail', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'ROLLBACK-A', 'call' => '9301', 'site' => 'A', 'plot' => 'Rollback Customer - Rollback Site - Plot 1'],
    ], '2099-11-05');
    DB::statement("CREATE TRIGGER fail_site BEFORE INSERT ON sites BEGIN SELECT RAISE(ABORT, 'forced site failure'); END");
    expect(fn () => source02Approve($office, $pilot, $pilot['sources'][0], 'NEW_CUSTOMER_AND_SITE'))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    DB::statement('DROP TRIGGER fail_site');
    expect(CustomerOrganisation::query()->where('name', 'Rollback Customer')->count())->toBe(0);

    $customer = CustomerOrganisation::factory()->create(['name' => 'Existing Customer', 'is_active' => true]);
    $sitePilot = source02Upload($office, [
        ['code' => 'ROLLBACK-B', 'call' => '9302', 'site' => 'B', 'plot' => 'Existing Customer - Rollback Site - Plot 2'],
    ], '2099-11-06');
    DB::statement("CREATE TRIGGER fail_binding BEFORE INSERT ON wald_binding_versions BEGIN SELECT RAISE(ABORT, 'forced binding failure'); END");
    expect(fn () => source02Approve($office, $sitePilot, $sitePilot['sources'][0], 'EXACT_CUSTOMER_NEW_SITE'))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    DB::statement('DROP TRIGGER fail_binding');
    expect(Site::query()->where('customer_organisation_id', $customer->id)->count())->toBe(0)
        ->and(DB::table('wald_source_bindings')->count())->toBe(0)
        ->and(DB::table('wald_pilot_events')->where('action', 'pilot_hierarchy_creation_approved')->count())->toBe(0);
});

it('shows grouped Office proposals and approves one through the guarded HTTP action', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'HTTP-NEW', 'call' => '9401', 'site' => 'A', 'plot' => 'HTTP Customer - HTTP Site - Plot 1'],
    ], '2099-11-07');
    $source = $pilot['sources'][0];
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
        ->assertOk()->assertSee('New customers')->assertSee('Create customer and site')
        ->assertSee('HTTP Customer')->assertSee('HTTP Site');
    $this->post(route('office.workspace.pilot-import.approve-hierarchy', $pilot['upload']), [
        'source_hash' => $source['hash'],
        'source_manifest_hash' => $upload->source_manifest_hash,
        'expected_epoch' => $upload->epoch,
        'expected_outcome' => 'NEW_CUSTOMER_AND_SITE',
        'expected_customer' => 'HTTP Customer',
        'expected_site' => 'HTTP Site',
        'confirmation' => 'APPROVE EXACT CUSTOMER AND SITE',
        'command_uuid' => (string) Str::uuid(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('office.workspace.pilot-import.show', $pilot['upload']))
        ->assertOk()->assertSee('Select this one site');
    $customer = CustomerOrganisation::query()->where('name', 'HTTP Customer')->firstOrFail();
    $site = Site::query()->where('customer_organisation_id', $customer->id)->where('name', 'HTTP Site')->firstOrFail();
    $this->get(route('office.workspace.customers.show', $customer))->assertOk();
    $this->get(route('office.workspace.sites.show', [$customer, $site]))->assertOk();
    expect(DB::table('projected_plots')->count())->toBe(0);
});

it('denies inactive and preview Office accounts from structural approval', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'DENY', 'call' => '9501', 'site' => 'A', 'plot' => 'Denied Customer - Denied Site - Plot 1'],
    ], '2099-11-08');
    $source = $pilot['sources'][0];
    $preview = source02Office(['is_preview_user' => true]);
    $inactive = source02Office(['is_active' => false]);
    foreach ([$preview, $inactive] as $denied) {
        expect(fn () => source02Approve($denied, $pilot, $source, 'NEW_CUSTOMER_AND_SITE'))
            ->toThrow(AuthorizationException::class);
    }
    expect(CustomerOrganisation::query()->where('name', 'Denied Customer')->count())->toBe(0);
});

it('does not create hierarchy from a superseded source revision', function (): void {
    $office = source02Office();
    $first = source02Upload($office, [
        ['code' => 'STALE-REV', 'call' => '9601', 'site' => 'A', 'plot' => 'Old Customer - Old Site - Plot 1'],
    ], '2099-11-09');
    source02Upload($office, [
        ['code' => 'STALE-REV', 'call' => '9602', 'site' => 'A', 'plot' => 'New Customer - New Site - Plot 2'],
    ], '2099-11-09', 'MORNING', $first['upload'], PilotImportWorkflow::REPLACEMENT_CONFIRMATION);
    expect(fn () => source02Approve($office, $first, $first['sources'][0], 'NEW_CUSTOMER_AND_SITE'))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    expect(CustomerOrganisation::query()->where('name', 'Old Customer')->count())->toBe(0);
});

it('rejects a discovery manifest without the current policy and resolver pins', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'OLD-PINS', 'call' => '9701', 'site' => 'A', 'plot' => 'Pinned Customer - Pinned Site - Plot 1'],
    ], '2099-11-10');
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
    unset($manifest['knowledge_policy'], $manifest['resolver_version']);
    DB::table('wald_pilot_uploads')->where('id', $upload->id)->update([
        'source_manifest' => Canonical::json($manifest),
        'source_manifest_hash' => Canonical::hash($manifest),
    ]);
    expect(fn () => source02Approve($office, $pilot, $pilot['sources'][0], 'NEW_CUSTOMER_AND_SITE'))
        ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
    expect(CustomerOrganisation::query()->where('name', 'Pinned Customer')->count())->toBe(0);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Pinned Customer', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Pinned Site', 'is_active' => true]);
    expect(fn () => (new PilotImportWorkflow)->select($office, $pilot['upload'], $pilot['sources'][0]['hash'],
        $site->uuid, (string) Str::uuid()))->toThrow(ImportConflict::class, 'pilot_source_manifest_stale');
    expect(DB::table('wald_source_bindings')->count())->toBe(0);
});

it('classifies all seven master source outcomes in one grouped read-only resolution', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $exactSite = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    $boundSite = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Barne Barton', 'is_active' => true]);
    $otherCustomer = CustomerOrganisation::factory()->create(['name' => 'Another Customer', 'is_active' => true]);
    Site::factory()->create(['customer_organisation_id' => $otherCustomer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    DB::table('projected_plots')->insert(['uuid' => (string) Str::uuid(), 'site_id' => $exactSite->id,
        'external_source' => 'test', 'external_identifier' => 'existing-exact-site-plot',
        'plot_reference' => '1', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
    $pilot = source02Upload($office, [
        ['code' => 'EXACT', 'call' => '5001', 'site' => 'Descriptive one', 'plot' => 'Vistry - Countryside 2D - Plot 1'],
        ['code' => 'BOUND', 'call' => '5002', 'site' => 'Descriptive two', 'plot' => 'Vistry - Barne Barton - Plot 1'],
        ['code' => 'NEW-SITE', 'call' => '5003', 'site' => 'Descriptive three', 'plot' => 'Vistry - Northam PH3 - Plot 7'],
        ['code' => 'NEW-CUSTOMER', 'call' => '5004', 'site' => 'Descriptive four', 'plot' => 'Lovell - Barne Barton - Plot 1'],
        ['code' => 'CONFLICT', 'call' => '5005', 'site' => 'Descriptive five', 'plot' => 'Vistry - Countryside 2D - Plot 2'],
        ['code' => 'CONFLICT', 'call' => '5006', 'site' => 'Descriptive five', 'plot' => 'Vistry - Northam PH3 - Plot 3'],
        ['code' => 'MALFORMED', 'call' => '5007', 'site' => 'Descriptive six', 'plot' => 'Unknown plot wording'],
        ['code' => 'BAD-BINDING', 'call' => '5008', 'site' => 'Descriptive seven', 'plot' => 'Vistry - Countryside 2D - Plot 4'],
        ['code' => 'EXCLUDED', 'call' => '5009', 'site' => 'Excluded only', 'plot' => 'No hierarchy', 'type' => 'CU4'],
    ], '2099-10-01');
    $sources = collect($pilot['sources'])->keyBy('customer_code');
    source02Bind($office, $sources['BOUND'], $boundSite);
    source02Bind($office, $sources['BAD-BINDING'], $boundSite);
    $summary = (new PilotImportWorkflow)->summary($office, $pilot['upload']);
    $resolved = collect($summary['sources'])->keyBy('customer_code');

    expect($resolved['EXACT']['resolution']['state'])->toBe('EXACT_CUSTOMER_EXACT_SITE')
        ->and($resolved['BOUND']['resolution']['state'])->toBe('EXACT_EXISTING_BINDING')
        ->and($resolved['NEW-SITE']['resolution']['state'])->toBe('EXACT_CUSTOMER_NEW_SITE')
        ->and($resolved['NEW-CUSTOMER']['resolution']['state'])->toBe('NEW_CUSTOMER_AND_SITE')
        ->and($resolved['CONFLICT']['resolution']['state'])->toBe('SOURCE_HIERARCHY_CONFLICT')
        ->and($resolved['CONFLICT']['resolution']['source_evidence'])->toHaveCount(2)
        ->and($resolved['MALFORMED']['resolution']['state'])->toBe('MALFORMED_HIERARCHY')
        ->and($resolved['BAD-BINDING']['resolution']['state'])->toBe('BINDING_CONFLICT')
        ->and($summary['resolution_summary']['source_sites'])->toBe(7)
        ->and($summary['resolution_summary']['excluded_rows'])->toBe(1)
        ->and($summary['resolution_summary']['automatically_matched_sites'])->toBe(2)
        ->and($summary['resolution_summary']['blockers'])->toBe(3)
        ->and($resolved['EXACT']['resolution']['site_uuid'])->toBe($exactSite->uuid)
        ->and($resolved['EXACT']['resolution']['plots_reuse'])->toBe(1)
        ->and($resolved['BOUND']['resolution']['plots_create'])->toBe(1)
        ->and($resolved['NEW-CUSTOMER']['resolution']['customer'])->toBe('Lovell');
});

it('resolves many exact sites without customer site binding or plot queries per source row', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $rows = [];
    for ($i = 1; $i <= 40; $i++) {
        Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => "Site $i", 'is_active' => true]);
        $rows[] = ['code' => "CODE-$i", 'call' => (string) (6000 + $i),
            'site' => "Description $i", 'plot' => "Vistry - Site $i - Plot $i"];
    }
    $pilot = source02Upload($office, $rows, '2099-10-02');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/\b(customer_organisations|sites|wald_source_bindings|wald_binding_versions|projected_plots)\b/i', $query->sql)) {
            $queries[] = $query->sql;
        }
    });
    $summary = (new PilotImportWorkflow)->summary($office, $pilot['upload']);

    expect($summary['resolution_summary']['automatically_matched_sites'])->toBe(40)
        ->and($summary['resolution_summary']['plots_create'])->toBe(40)
        ->and(count($queries))->toBeLessThanOrEqual(8);
});

it('creates an exact binding only inside the successful selected-site transaction', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $right = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    $wrong = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Different Site', 'is_active' => true]);
    $pilot = source02Upload($office, [
        ['code' => 'FNA2563', 'call' => '7001', 'site' => 'Descriptive name', 'plot' => 'Vistry - Countryside 2D - Plot Com 4'],
    ], '2099-10-03');
    $source = $pilot['sources'][0];
    expect(fn () => (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $wrong->uuid, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'source_binding_customer_ownership_conflict');
    expect(DB::table('wald_source_bindings')->count())->toBe(0)
        ->and(DB::table('wald_pilot_selections')->count())->toBe(0);

    $selected = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $right->uuid, (string) Str::uuid());
    expect($selected['selection'])->toBeString()
        ->and(DB::table('wald_source_bindings')->count())->toBe(1)
        ->and(DB::table('wald_binding_versions')->count())->toBe(1)
        ->and(DB::table('wald_pilot_selections')->count())->toBe(1)
        ->and(DB::table('wald_pilot_events')->where('action', 'pilot_exact_binding_activated')->count())->toBe(1)
        ->and((new PilotImportWorkflow)->summary($office, $pilot['upload'])['sources'][0]['resolution']['state'])
        ->toBe('EXACT_EXISTING_BINDING');
});

it('matches only controlled case and whitespace differences through staging', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    $pilot = source02Upload($office, [
        ['code' => 'NORMALIZED', 'call' => '7101', 'site' => 'Unrelated description', 'plot' => 'vistry - countryside  2d - Plot Com 4'],
    ], '2099-10-04');
    expect($pilot['sources'][0]['resolution']['state'])->toBe('EXACT_CUSTOMER_EXACT_SITE');
    $selected = (new PilotImportWorkflow)->select($office, $pilot['upload'], $pilot['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selected['scope'], DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail());
    $plot = (new BackendStore)->payload(DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->firstOrFail())['facts']['plot'];
    expect($plot)->toBe('Com 4');

    $fuzzy = source02Upload($office, [
        ['code' => 'FUZZY', 'call' => '7102', 'site' => 'Unrelated description', 'plot' => 'Vistry Partnerships - Countryside 2D - Plot 5'],
    ], '2099-10-05');
    expect($fuzzy['sources'][0]['resolution']['state'])->toBe('NEW_CUSTOMER_AND_SITE');
});

it('preserves an existing exact draft for Office activation', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    $pilot = source02Upload($office, [
        ['code' => 'DRAFT', 'call' => '7201', 'site' => 'Descriptive name', 'plot' => 'Vistry - Countryside 2D - Plot 8'],
    ], '2099-10-06');
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, 'CUSTOMER_CODE', 'DRAFT', 'Existing reviewed draft.', (string) Str::uuid());
    $summary = (new PilotImportWorkflow)->summary($office, $pilot['upload']);
    expect($summary['sources'][0]['resolution']['state'])->toBe('EXACT_CUSTOMER_EXACT_SITE')
        ->and($summary['sources'][0]['draft'])->not->toBeNull();
    expect(fn () => (new PilotImportWorkflow)->select($office, $pilot['upload'], $pilot['sources'][0]['hash'], $site->uuid, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'source_site_binding_required');
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'],
        $draft['epoch'], 'Activate reviewed draft.', (string) Str::uuid());
    expect((new PilotImportWorkflow)->summary($office, $pilot['upload'])['sources'][0]['resolution']['state'])
        ->toBe('EXACT_EXISTING_BINDING');
});

it('resolves composite customer site and named plots after exact Office binding', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2563', 'call' => '1312', 'site' => 'Sherford C & K - Vistry Partnerships', 'plot' => 'Vistry – Countryside 2D – Plot 776'],
        ['code' => 'FNA2563', 'call' => '1313', 'site' => 'Sherford C & K - Vistry Partnerships', 'plot' => 'Vistry-Countryside 2D-Plot Com 4'],
        ['code' => 'FNA2563', 'call' => '1314', 'site' => 'Sherford C & K - Vistry Partnerships', 'plot' => 'irrelevant customer care', 'type' => 'CU4'],
    ], '2099-09-01');
    $source = $pilot['sources'][0];
    expect($source['hierarchy']['customer'])->toBe('Vistry')
        ->and($source['hierarchy']['site'])->toBe('Countryside 2D')
        ->and($source['hierarchy']['valid_rows'])->toBe(2)
        ->and($source['hierarchy']['invalid_rows'])->toBe(0)
        ->and($source['hierarchy_proposal']['state'])->toBe('NEW_CUSTOMER_FOUND');

    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    expect((new PilotImportWorkflow)->summary($office, $pilot['upload'])['sources'][0]['hierarchy_proposal']['state'])
        ->toBe('NEW_SITE_FOUND');
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    expect((new PilotImportWorkflow)->summary($office, $pilot['upload'])['sources'][0]['hierarchy_proposal']['state'])
        ->toBe('EXACT_SITE_FOUND');
    source02Bind($office, $source, $site);
    $selected = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selected['scope'], DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail());
    $plots = DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->orderBy('ordinal')->get()
        ->map(fn (object $row): ?string => (new BackendStore)->payload($row)['facts']['plot'] ?? null)->all();
    expect($plots)->toContain('776', 'Com 4');
});

it('blocks a source binding that disagrees with the parsed customer and site', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2563', 'call' => '1312', 'site' => 'Sherford C & K - Vistry Partnerships', 'plot' => 'Vistry – Countryside 2D – Plot 776'],
    ], '2099-09-02');
    $wrongCustomer = CustomerOrganisation::factory()->create(['name' => 'Wrong Customer']);
    $wrongSite = Site::factory()->create(['customer_organisation_id' => $wrongCustomer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    source02Bind($office, $pilot['sources'][0], $wrongSite);
    expect(fn () => (new PilotImportWorkflow)->select($office, $pilot['upload'], $pilot['sources'][0]['hash'], $wrongSite->uuid, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'source_binding_customer_ownership_conflict');
});

it('maps the confirmed Northam mixed separator format directly to its full plot number', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2561', 'call' => '2251', 'site' => 'Northam source label', 'plot' => 'Vistry - Northam PH3-33842'],
    ], '2099-09-05');
    $source = $pilot['sources'][0];
    expect($source['hierarchy']['customer'])->toBe('Vistry')
        ->and($source['hierarchy']['site'])->toBe('Northam PH3')
        ->and($source['hierarchy']['valid_rows'])->toBe(1)
        ->and($source['hierarchy_issues'])->toBe([]);

    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Northam PH3', 'is_active' => true]);
    source02Bind($office, $source, $site);
    $selected = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selected['scope'], DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail());
    $plot = (new BackendStore)->payload(DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->firstOrFail())['facts']['plot'];
    expect($plot)->toBe('33842');
});

it('requires audited Office confirmation for an ambiguous included hierarchy row', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2563', 'call' => '1312', 'site' => 'Sherford C & K - Vistry Partnerships', 'plot' => 'Vistry – Countryside 2D – Plot 776'],
        ['code' => 'FNA2563', 'call' => '1313', 'site' => 'Sherford C & K - Vistry Partnerships', 'plot' => 'Vistry - Countryside 2D-Plot 777'],
    ], '2099-09-03');
    $source = $pilot['sources'][0];
    $issue = $source['hierarchy_issues'][0];
    expect($source['hierarchy_proposal']['state'])->toBe('HIERARCHY_REVIEW_REQUIRED')
        ->and($issue['rows'])->toBe([3]);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    source02Bind($office, $source, $site);
    expect(fn () => (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'source_binding_customer_ownership_conflict');

    $answers = new HierarchyClarifications;
    $external = User::factory()->create(['portal_role_id' => PortalRole::query()->where('identifier', 'site_manager')->value('id'), 'is_active' => true]);
    expect(fn () => $answers->answer($external, $pilot['upload'], $source['hash'], $issue['hash'], 'Vistry', 'Countryside 2D', '777', (string) Str::uuid()))
        ->toThrow(AuthorizationException::class);
    expect(DB::table('wald_pilot_hierarchy_answers')->count())->toBe(0);
    $answers->answer($office, $pilot['upload'], $source['hash'], $issue['hash'], 'Vistry', 'Countryside 2D', '777', (string) Str::uuid());
    $answers->answer($office, $pilot['upload'], $source['hash'], $issue['hash'], 'Vistry', 'Countryside 2D', '777', (string) Str::uuid());
    expect((new PilotImportWorkflow)->summary($office, $pilot['upload'])['sources'][0]['hierarchy_proposal']['state'])
        ->toBe('EXACT_SITE_FOUND')
        ->and(DB::table('wald_pilot_hierarchy_answers')->count())->toBe(1)
        ->and(DB::table('wald_pilot_events')->where('action', 'pilot_hierarchy_answered')->count())->toBe(1);
    $selected = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selected['scope'], DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail());
    $plots = DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->orderBy('ordinal')->get()
        ->map(fn (object $row): string => (new BackendStore)->payload($row)['facts']['plot'])->all();
    expect($plots)->toBe(['776', '777']);
    expect(fn () => $answers->answer($office, $pilot['upload'], $source['hash'], $issue['hash'], 'Vistry', 'Countryside 2D', '778', (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'pilot_upload_not_selectable');
});

it('refuses selection from an older discovery manifest after the hierarchy contract changes', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2563', 'call' => '1312', 'site' => 'Sherford source name', 'plot' => 'Vistry - Countryside 2D - Plot 776'],
    ], '2099-09-04');
    $manifest = $pilot['manifest'];
    $manifest['schema'] = 'customerapp.wald-pilot-discovery.v3';
    DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->update(['source_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR)]);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Vistry', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Countryside 2D', 'is_active' => true]);
    source02Bind($office, $pilot['sources'][0], $site);
    expect(fn () => (new PilotImportWorkflow)->select($office, $pilot['upload'], $pilot['sources'][0]['hash'], $site->uuid, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'pilot_source_manifest_stale');
});

it('uses CustomerCode as identity while retaining changed source names as review evidence', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2664', 'call' => '1001', 'site' => 'Little Cotton Farm', 'plot' => '1'],
        ['code' => 'FNA2664', 'call' => '1002', 'site' => 'Little Cotton Farm corrected', 'plot' => '2'],
        ['code' => 'FNA2649', 'call' => '1003', 'site' => 'Little Cotton Farm', 'plot' => '3'],
    ], '2099-02-01');

    expect($pilot['sources'])->toHaveCount(2);
    $first = collect($pilot['sources'])->firstWhere('customer_code', 'FNA2664');
    $second = collect($pilot['sources'])->firstWhere('customer_code', 'FNA2649');
    expect($first['kind'])->toBe('CUSTOMER_CODE')
        ->and($first['identity'])->toBe('FNA2664')
        ->and($first['rows'])->toBe(2)
        ->and($first['observed_site_names'])->toBe(['Little Cotton Farm', 'Little Cotton Farm corrected'])
        ->and($first['warnings'])->toContain('SOURCE_SITE_NAME_VARIATION')
        ->and($first['binding'])->toBeNull()
        ->and($second['identity'])->toBe('FNA2649')
        ->and($second['hash'])->not->toBe($first['hash']);
});

it('accepts the exact Customer Number header as CustomerCode without relaxing binding or header checks', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [
        ['code' => 'FNA2664', 'call' => '1001', 'site' => 'Little Cotton Farm', 'plot' => 'Baker Estates Ltd - Little Cotton Farm - Plot 1'],
    ], '2099-02-03', customerCodeHeader: 'Customer Number');
    $source = $pilot['sources'][0];

    expect($source['kind'])->toBe('CUSTOMER_CODE')
        ->and($source['customer_code'])->toBe('FNA2664')
        ->and($source['identity'])->toBe('FNA2664')
        ->and($source['rows'])->toBe(1);

    $customer = CustomerOrganisation::factory()->create(['name' => 'Baker Estates Ltd']);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Little Cotton Farm', 'is_active' => true]);
    $workflow = new PilotImportWorkflow;
    expect($workflow->summary($office, $pilot['upload'])['sources'][0]['resolution']['state'])
        ->toBe('EXACT_CUSTOMER_EXACT_SITE');
    $selection = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    expect($selection['selection'])->toBeString()
        ->and(DB::table('wald_source_bindings')->count())->toBe(1)
        ->and(DB::table('wald_pilot_events')->where('action', 'pilot_exact_binding_activated')->count())->toBe(1);

    expect(fn () => source02Upload($office, [
        ['code' => 'FNA2664', 'call' => '1002', 'site' => 'Little Cotton Farm', 'plot' => '2'],
    ], '2099-02-04', customerCodeHeader: 'Customer Numbers'))
        ->toThrow(ImportConflict::class, 'customer_code_missing');

    $ambiguous = UploadedFile::fake()->createWithContent('master.csv',
        "CustomerNo,Customer Number,Call No.,Site Name,Plot Ref,Call Type,Complete,VS\n".
        "FNA2664,FNA2664,1003,Little Cotton Farm,3,PC1,No,2\n");
    expect(fn () => $workflow->upload($office, $ambiguous, new ExportOrder('2099-02-05', 'MORNING'),
        ExportOrder::CONFIRMATION, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'customer_code_column_ambiguous');
});

it('blocks a new master export when CustomerCode is missing instead of falling back to Site Name', function (): void {
    $office = source02Office();

    expect(fn () => source02Upload($office, [
        ['call' => '1001', 'site' => 'Looks Like A Site', 'plot' => '1'],
    ], '2099-02-02', withCustomerCode: false))->toThrow(ImportConflict::class, 'customer_code_missing');

    expect(DB::table('wald_pilot_uploads')->value('state'))->toBe('FAILED')
        ->and(DB::table('wald_source_bindings')->count())->toBe(0);
});

it('requires one exact site binding and permits the same Plot Ref on different sites', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'TEST — Acme Developments']);
    $willow = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'TEST — Willow Park', 'is_active' => true]);
    $meadow = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'TEST — Meadow View', 'is_active' => true]);
    $pilot = source02Upload($office, [
        ['code' => 'ACME-WILLOW', 'call' => '8101', 'site' => 'Willow Park Source', 'plot' => '101'],
        ['code' => 'ACME-MEADOW', 'call' => '8201', 'site' => 'Meadow View Source', 'plot' => '101'],
    ], '2099-03-01');
    $workflow = new PilotImportWorkflow;
    $review = new ImportReview;
    $willowSource = collect($pilot['sources'])->firstWhere('identity', 'ACME-WILLOW');
    $meadowSource = collect($pilot['sources'])->firstWhere('identity', 'ACME-MEADOW');

    expect(fn () => $workflow->select($office, $pilot['upload'], $willowSource['hash'], $willow->uuid, (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'source_site_binding_required');

    foreach ([[$willowSource, $willow], [$meadowSource, $meadow]] as [$source, $site]) {
        source02Bind($office, $source, $site);
        $selection = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = source02Analyse($office, $selection['scope'], DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
        $preview = $review->preview($office, $selection['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
        $payload = json_decode(DB::table('wald_import_previews')->where('uuid', $preview['preview'])->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        expect($preview['blockers'])->toBe([])
            ->and($payload['projection']['target']['plots'])->toHaveCount(1)
            ->and($payload['projection']['target']['site']['uuid'])->toBe($site->uuid)
            ->and($payload['projection']['target']['plots'])->toBe([101 => 'CREATE']);
        $review->approve($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        $review->commit($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    }

    expect(DB::table('projected_plots')->where('plot_reference', '101')->count())->toBe(2)
        ->and(DB::table('projected_plots')->where('site_id', $willow->id)->where('plot_reference', '101')->exists())->toBeTrue()
        ->and(DB::table('projected_plots')->where('site_id', $meadow->id)->where('plot_reference', '101')->exists())->toBeTrue();

    $later = source02Upload($office, [
        ['code' => 'ACME-WILLOW', 'call' => '8102', 'site' => 'Willow Park Source', 'plot' => '101', 'type' => ''],
    ], '2099-03-02');
    $selection = $workflow->select($office, $later['upload'], $later['sources'][0]['hash'], $willow->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selection['scope'], DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $preview = $review->preview($office, $selection['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $payload = json_decode(DB::table('wald_import_previews')->where('uuid', $preview['preview'])->value('payload'), true, flags: JSON_THROW_ON_ERROR);
    expect($payload['projection']['target']['plots'])->toBe([101 => 'REUSE'])
        ->and(DB::table('projected_plots')->where('site_id', $willow->id)->where('plot_reference', '101')->count())->toBe(1);
    $review->approve($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $review->commit($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());

    expect(DB::table('projected_plots')->where('site_id', $willow->id)->where('plot_reference', '101')->count())->toBe(1)
        ->and(DB::table('wald_import_receipts')->where('run_id', $run->id)->exists())->toBeTrue();
});

it('blocks a CallNo from moving its plot history to another site', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create();
    $firstSite = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $otherSite = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $workflow = new PilotImportWorkflow;
    $review = new ImportReview;

    $first = source02Upload($office, [['code' => 'FIRST-SITE', 'call' => '8301', 'site' => 'First', 'plot' => '101']], '2099-03-02');
    source02Bind($office, $first['sources'][0], $firstSite);
    $selection = $workflow->select($office, $first['upload'], $first['sources'][0]['hash'], $firstSite->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selection['scope'], DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $preview = $review->preview($office, $selection['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $review->approve($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $review->commit($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());

    $second = source02Upload($office, [['code' => 'OTHER-SITE', 'call' => '8301', 'site' => 'Other', 'plot' => '101']], '2099-03-03');
    source02Bind($office, $second['sources'][0], $otherSite);
    $selection = $workflow->select($office, $second['upload'], $second['sources'][0]['hash'], $otherSite->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selection['scope'], DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $blocked = $review->preview($office, $selection['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());

    expect($blocked['blockers'])->toBe(['source_row_identity_changed'])
        ->and(DB::table('projected_plots')->where('site_id', $otherSite->id)->count())->toBe(0)
        ->and(DB::table('wald_source_rows')->where('call_number', '8301')->value('site_id'))->toBe($firstSite->id);
});

it('deduplicates identical exports and requires confirmation for a non-failed same-slot revision', function (): void {
    $office = source02Office();
    $rows = [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']];
    $first = source02Upload($office, $rows, '2099-02-03', 'AFTERNOON');

    expect(fn () => source02Upload($office, $rows, '2099-02-03', 'AFTERNOON'))
        ->toThrow(IdenticalPilotImportConflict::class, 'identical_pilot_import_exists');
    expect(DB::table('wald_pilot_uploads')->count())->toBe(1);

    $changed = [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A corrected', 'plot' => '1']];
    expect(fn () => source02Upload($office, $changed, '2099-02-03', 'AFTERNOON'))
        ->toThrow(PilotReplacementConfirmationRequired::class, 'pilot_replacement_confirmation_required');
    expect(DB::table('wald_pilot_uploads')->count())->toBe(1);

    expect(fn () => source02Upload($office, $changed, '2099-02-03', 'AFTERNOON', (string) Str::uuid(), PilotImportWorkflow::REPLACEMENT_CONFIRMATION))
        ->toThrow(ImportConflict::class, 'invalid_pilot_predecessor');

    $second = source02Upload($office, $changed, '2099-02-03', 'AFTERNOON', $first['upload'], PilotImportWorkflow::REPLACEMENT_CONFIRMATION);
    $old = DB::table('wald_pilot_uploads')->where('uuid', $first['upload'])->firstOrFail();
    $new = DB::table('wald_pilot_uploads')->where('uuid', $second['upload'])->firstOrFail();
    $payload = json_decode(DB::table('wald_pilot_events')->where('pilot_upload_id', $new->id)->where('action', 'pilot_upload_created')->value('payload'), true, flags: JSON_THROW_ON_ERROR);

    expect($old->state)->toBe('SUPERSEDED')
        ->and($new->revision)->toBe(2)
        ->and($new->predecessor_upload_id)->toBe($old->id)
        ->and($payload['predecessor_state'])->toBe('READY')
        ->and($payload['confirmation_required'])->toBeTrue()
        ->and($payload['confirmation_received'])->toBeTrue()
        ->and($second['revisions'])->toHaveCount(2)
        ->and($second['revisions'][0]['current'])->toBeTrue();
});

it('fails closed when a master-export replacement names a missing or unrelated predecessor', function (): void {
    $office = source02Office();
    $first = source02Upload($office, [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-10');
    $unrelated = source02Upload($office, [['code' => 'OTHER-CODE', 'call' => '2001', 'site' => 'Site B', 'plot' => '2']], '2099-02-11');
    $changed = [['code' => 'FNA2664', 'call' => '1002', 'site' => 'Site A', 'plot' => '3']];

    expect(fn () => source02Upload(
        $office,
        $changed,
        '2099-02-10',
        predecessor: (string) Str::uuid(),
        replacementConfirmation: PilotImportWorkflow::REPLACEMENT_CONFIRMATION,
    ))->toThrow(ImportConflict::class, 'invalid_pilot_predecessor');
    expect(fn () => source02Upload(
        $office,
        $changed,
        '2099-02-10',
        predecessor: $unrelated['upload'],
        replacementConfirmation: PilotImportWorkflow::REPLACEMENT_CONFIRMATION,
    ))->toThrow(ImportConflict::class, 'invalid_pilot_predecessor');

    expect(DB::table('wald_pilot_uploads')->count())->toBe(2)
        ->and(DB::table('wald_pilot_uploads')->where('uuid', $first['upload'])->value('state'))->toBe('READY')
        ->and(DB::table('wald_import_receipts')->count())->toBe(0);
});

it('automatically creates a retained successor revision after a failed upload', function (): void {
    $office = source02Office();
    expect(fn () => source02Upload($office, [['call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-04', withCustomerCode: false))
        ->toThrow(ImportConflict::class, 'customer_code_missing');
    $failed = DB::table('wald_pilot_uploads')->firstOrFail();

    $successor = source02Upload($office, [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-04');
    $failed = DB::table('wald_pilot_uploads')->where('id', $failed->id)->firstOrFail();
    $current = DB::table('wald_pilot_uploads')->where('uuid', $successor['upload'])->firstOrFail();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    source02Bind($office, $successor['sources'][0], $site);
    $selection = (new PilotImportWorkflow)->select($office, $successor['upload'], $successor['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selection['scope'], DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $review = new ImportReview;
    $preview = $review->preview($office, $selection['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $review->approve($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $receipt = $review->commit($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());

    expect($failed->state)->toBe('SUPERSEDED')
        ->and($current->revision)->toBe(2)
        ->and($current->predecessor_upload_id)->toBe($failed->id)
        ->and($current->state)->toBe('READY')
        ->and($run->predecessor_id)->toBeNull()
        ->and($preview['blockers'])->toBe([])
        ->and($receipt['run'])->toBe($run->uuid)
        ->and($receipt['predecessor_receipt'])->toBeNull()
        ->and(DB::table('wald_import_receipts')->count())->toBe(1)
        ->and(DB::table('wald_pilot_uploads')->count())->toBe(2);
    $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $successor['upload']))
        ->assertOk()
        ->assertSee('Revision 1 · Failed / Superseded');
});

it('permits retrying identical bytes after a failed discovery without duplicate suppression', function (): void {
    $office = source02Office();
    $rows = [['call' => '1001', 'site' => 'Site A', 'plot' => '1']];

    expect(fn () => source02Upload($office, $rows, '2099-02-06', withCustomerCode: false))
        ->toThrow(ImportConflict::class, 'customer_code_missing');
    $first = DB::table('wald_pilot_uploads')->firstOrFail();

    expect(fn () => source02Upload($office, $rows, '2099-02-06', withCustomerCode: false))
        ->toThrow(ImportConflict::class, 'customer_code_missing');

    $uploads = DB::table('wald_pilot_uploads')->orderBy('revision')->get();
    expect($uploads)->toHaveCount(2)
        ->and($uploads[0]->state)->toBe('SUPERSEDED')
        ->and($uploads[1]->state)->toBe('FAILED')
        ->and($uploads[1]->revision)->toBe(2)
        ->and($uploads[1]->predecessor_upload_id)->toBe($first->id)
        ->and($uploads[1]->workbook_hash)->toBe($first->workbook_hash);
});

it('previews and commits a corrected successor after an uncommitted predecessor without fabricating a receipt', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create(['name' => 'TEST — Acme Developments']);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'TEST — Willow Park', 'is_active' => true]);
    $workflow = new PilotImportWorkflow;
    $review = new ImportReview;
    $rows = [
        ['code' => 'ACME-WILLOW-E2E', 'call' => '9101', 'site' => 'Willow Park', 'plot' => 'WALD-E2E-01', 'type' => 'CC1'],
        ['code' => 'ACME-WILLOW-E2E', 'call' => '9102', 'site' => 'Willow Park', 'plot' => 'WALD-E2E-02', 'type' => 'CM1'],
    ];

    $first = source02Upload($office, $rows, '2099-02-09');
    source02Bind($office, $first['sources'][0], $site);
    $selection1 = $workflow->select($office, $first['upload'], $first['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run1 = source02Analyse($office, $selection1['scope'], DB::table('wald_import_runs')->where('uuid', $selection1['run'])->firstOrFail());
    $preview1 = $review->preview($office, $selection1['scope'], $run1->uuid, (int) $run1->epoch, (string) Str::uuid());

    $rows[0]['site'] = 'Willow Park corrected';
    $second = source02Upload(
        $office,
        $rows,
        '2099-02-09',
        predecessor: $first['upload'],
        replacementConfirmation: PilotImportWorkflow::REPLACEMENT_CONFIRMATION,
    );
    $selection2 = $workflow->select($office, $second['upload'], $second['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run2 = source02Analyse($office, $selection2['scope'], DB::table('wald_import_runs')->where('uuid', $selection2['run'])->firstOrFail());
    $preview2 = $review->preview($office, $selection2['scope'], $run2->uuid, (int) $run2->epoch, (string) Str::uuid());

    expect(DB::table('wald_import_runs')->where('id', $run1->id)->value('state'))->toBe('SUPERSEDED')
        ->and($run2->predecessor_id)->toBe($run1->id)
        ->and($preview2['blockers'])->toBe([]);
    expect(fn () => $review->approve($office, $selection1['scope'], $run1->uuid, $preview1['preview'], $preview1['hash'], (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'review_approval_state_conflict');

    $review->approve($office, $selection2['scope'], $run2->uuid, $preview2['preview'], $preview2['hash'], (string) Str::uuid());
    $receipt = $review->commit($office, $selection2['scope'], $run2->uuid, $preview2['preview'], $preview2['hash'], (string) Str::uuid());
    $retry = $review->commit($office, $selection2['scope'], $run2->uuid, $preview2['preview'], $preview2['hash'], (string) Str::uuid());

    expect($receipt['run'])->toBe($run2->uuid)
        ->and($receipt['receipt'])->toBe($retry['receipt'])
        ->and($receipt['predecessor_receipt'])->toBeNull()
        ->and(DB::table('wald_import_receipts')->where('run_id', $run1->id)->exists())->toBeFalse()
        ->and(DB::table('wald_import_receipts')->where('run_id', $run2->id)->count())->toBe(1)
        ->and(DB::table('wald_pilot_uploads')->where('id', $second['id'])->value('revision'))->toBe(2)
        ->and(DB::table('projected_plots')->where('site_id', $site->id)->orderBy('plot_reference')->pluck('plot_reference')->all())
        ->toBe(['WALD-E2E-01', 'WALD-E2E-02'])
        ->and(DB::table('projected_plots')->where('site_id', '!=', $site->id)->count())->toBe(0);
});

it('stales an older uncommitted preview and preserves committed partial projections', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $first = source02Upload($office, [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-05');
    source02Bind($office, $first['sources'][0], $site);
    $selected = (new PilotImportWorkflow)->select($office, $first['upload'], $first['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selected['scope'], DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail());
    $preview = (new ImportReview)->preview($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());

    $second = source02Upload(
        $office,
        [['code' => 'FNA2664', 'call' => '1002', 'site' => 'Site A', 'plot' => '2']],
        '2099-02-05',
        predecessor: $first['upload'],
        replacementConfirmation: PilotImportWorkflow::REPLACEMENT_CONFIRMATION,
    );
    expect(DB::table('wald_import_runs')->where('id', $run->id)->value('state'))->toBe('SUPERSEDED');
    expect(fn () => (new ImportReview)->approve($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid()))
        ->toThrow(ImportConflict::class, 'review_approval_state_conflict');

    $newSelection = (new PilotImportWorkflow)->select($office, $second['upload'], $second['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $newRun = DB::table('wald_import_runs')->where('uuid', $newSelection['run'])->firstOrFail();
    expect($newRun->predecessor_id)->toBe($run->id);
});

it('retains committed predecessor data when a partial replacement omits it', function (): void {
    $office = source02Office();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $workflow = new PilotImportWorkflow;
    $review = new ImportReview;

    $first = source02Upload($office, [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-07');
    source02Bind($office, $first['sources'][0], $site);
    $selection = $workflow->select($office, $first['upload'], $first['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run = source02Analyse($office, $selection['scope'], DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $preview = $review->preview($office, $selection['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
    $review->approve($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $firstReceipt = $review->commit($office, $selection['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $firstReceiptRow = DB::table('wald_import_receipts')->where('run_id', $run->id)->firstOrFail();

    $second = source02Upload(
        $office,
        [['code' => 'FNA2664', 'call' => '1002', 'site' => 'Site A renamed', 'plot' => '2']],
        '2099-02-07',
        predecessor: $first['upload'],
        replacementConfirmation: PilotImportWorkflow::REPLACEMENT_CONFIRMATION,
    );
    expect(DB::table('projected_plots')->where('site_id', $site->id)->pluck('plot_reference')->all())->toBe(['1']);
    $selection2 = $workflow->select($office, $second['upload'], $second['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
    $run2 = source02Analyse($office, $selection2['scope'], DB::table('wald_import_runs')->where('uuid', $selection2['run'])->firstOrFail());
    expect($run2->predecessor_id)->toBe($run->id);
    $preview2 = $review->preview($office, $selection2['scope'], $run2->uuid, (int) $run2->epoch, (string) Str::uuid());
    $review->approve($office, $selection2['scope'], $run2->uuid, $preview2['preview'], $preview2['hash'], (string) Str::uuid());
    $secondReceipt = $review->commit($office, $selection2['scope'], $run2->uuid, $preview2['preview'], $preview2['hash'], (string) Str::uuid());
    $retainedReceiptRow = DB::table('wald_import_receipts')->where('run_id', $run->id)->firstOrFail();

    expect(DB::table('projected_plots')->where('site_id', $site->id)->orderBy('plot_reference')->pluck('plot_reference')->all())->toBe(['1', '2'])
        ->and(DB::table('wald_import_receipts')->count())->toBe(2)
        ->and($secondReceipt['predecessor_receipt'])->toBe($firstReceipt['receipt'])
        ->and($retainedReceiptRow->uuid)->toBe($firstReceiptRow->uuid)
        ->and($retainedReceiptRow->payload_hash)->toBe($firstReceiptRow->payload_hash)
        ->and($retainedReceiptRow->payload)->toBe($firstReceiptRow->payload)
        ->and(DB::table('wald_import_runs')->where('id', $run->id)->value('state'))->toBe('SUPERSEDED');
});

it('shows current revision and CustomerCode evidence in the Office UI only', function (): void {
    $office = source02Office();
    $pilot = source02Upload($office, [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-06');

    $this->actingAs($office)->get(route('office.workspace.imports'))
        ->assertOk()
        ->assertSee('Revision 1')
        ->assertSee('Ready to select a site')
        ->assertSee('Replace existing upload')
        ->assertSee('Replace existing upload?')
        ->assertSee('replacementDialog', escape: false);
    $this->actingAs($office)->get(route('office.workspace.pilot-import.show', $pilot['upload']))
        ->assertOk()
        ->assertSee('CustomerCode FNA2664')
        ->assertSee('Source Site Name')
        ->assertSee('Site A');

    foreach (['site_manager', 'assistant_site_manager', 'finishing_foreman'] as $role) {
        $external = User::factory()->create([
            'portal_role_id' => PortalRole::query()->where('identifier', $role)->value('id'),
            'is_active' => true,
        ]);
        $this->actingAs($external)->get(route('office.workspace.imports'))->assertForbidden();
    }
});

it('enforces the retained-history replacement confirmation through the HTTP boundary', function (): void {
    $office = source02Office();
    $first = source02Upload($office, [['code' => 'FNA2664', 'call' => '1001', 'site' => 'Site A', 'plot' => '1']], '2099-02-08');
    $changed = [['code' => 'FNA2664', 'call' => '1002', 'site' => 'Site A', 'plot' => '2']];
    $request = [
        'workbook' => source02Workbook($changed),
        'export_date' => '2099-02-08',
        'export_slot' => 'MORNING',
        'confirmation' => ExportOrder::CONFIRMATION,
        'command_uuid' => (string) Str::uuid(),
        'predecessor' => $first['upload'],
    ];

    $this->actingAs($office)->post(route('office.workspace.pilot-import.upload'), $request)
        ->assertRedirect()
        ->assertSessionHasErrors('import');
    expect(DB::table('wald_pilot_uploads')->count())->toBe(1);

    $request['workbook'] = source02Workbook($changed);
    $request['command_uuid'] = (string) Str::uuid();
    $request['replacement_confirmation'] = PilotImportWorkflow::REPLACEMENT_CONFIRMATION;
    $this->actingAs($office)->post(route('office.workspace.pilot-import.upload'), $request)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DB::table('wald_pilot_uploads')->count())->toBe(2)
        ->and(DB::table('wald_pilot_uploads')->max('revision'))->toBe(2);
});
