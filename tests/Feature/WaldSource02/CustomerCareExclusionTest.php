<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
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

function careOffice(): User
{
    return User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
}

function careUpload(User $office, array $rows, string $date): array
{
    return (new PilotImportWorkflow)->upload($office,
        UploadedFile::fake()->createWithContent('master.csv',
            "CustomerNo,Call No.,Site Name,Plot Ref,Call Type,VS\n".implode("\n", $rows)."\n"),
        new ExportOrder($date, 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
}

function careAnalyse(User $office, KnowledgeScope $scope, object $run): object
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
                (new AnswerClarification)->handle($office, $scope, $context->uuid, $question['uuid'], $question['sequence'], $question['evidence']['candidates'][0]['id'], 'Reviewed exact source header.', (string) Str::uuid());
            }
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    }

    return DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
}

it('excludes every CU4 row from discovery and projection while retaining private audit evidence', function (): void {
    $office = careOffice();
    $pilot = careUpload($office, [
        'CODE-1,1001,Example Site,Plot 1,PC1,2',
        'CODE-1,1002,Example Site,Plot 1,CU4,99',
        'CODE-1,1003,Example Site,Plot 2, cu4 ,bad',
        ',1004,Customer Care Only,Plot 3,CU4,3',
    ], '2099-10-01');

    expect($pilot['manifest']['record_count'])->toBe(4)
        ->and($pilot['manifest']['included_count'])->toBe(1)
        ->and($pilot['manifest']['excluded_count'])->toBe(3)
        ->and($pilot['sources'])->toHaveCount(1)
        ->and($pilot['sources'][0]['rows'])->toBe(1);

    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $binding = new SourceBindingService;
    $source = $pilot['sources'][0];
    $draft = $binding->draft($office, $scope, $source['kind'], $source['identity'], 'Exact source binding.', (string) Str::uuid());
    $binding->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed exact binding.', (string) Str::uuid());
    $selection = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = careAnalyse($office, $scope, DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $stage = DB::table('wald_import_stages')->where('id', $run->stage_id)->firstOrFail();
    $rows = DB::table('wald_staged_rows')->where('stage_id', $stage->id)->orderBy('ordinal')->pluck('payload')
        ->map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR))->all();
    expect((int) $stage->blocked_count)->toBe(0)
        ->and($rows)->toHaveCount(3)
        ->and(collect($rows)->where('excluded', true)->count())->toBe(2)
        ->and($rows[1]['provenance']['selection']['approvals'])->toContain('DEC-069')
        ->and($rows[2]['provenance']['raw_call_type'])->toBe(' cu4 ')
        ->and($rows[2]['issues'])->toBe([]);

    $review = new ImportReview;
    $preview = $review->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    expect($preview['blockers'])->toBe([]);
    $review->approve($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $review->commit($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    expect(DB::table('projected_plots')->where('site_id', $site->id)->count())->toBe(1)
        ->and(DB::table('wald_visit_observations')->where('run_id', $run->id)->count())->toBe(1)
        ->and(DB::table('wald_import_receipts')->where('run_id', $run->id)->exists())->toBeTrue();
});

it('keeps other unknown CU codes blocked and has no selectable unit for only CU4 rows', function (): void {
    $office = careOffice();
    expect(fn () => careUpload($office, ['CODE-1,1001,Example Site,Plot 1,CU4,3'], '2099-10-02'))
        ->toThrow(ImportConflict::class, 'no_source_records');

    $pilot = careUpload($office, ['CODE-1,1002,Example Site,Plot 1,PC1,2', 'CODE-1,1003,Example Site,Plot 2,CU1,3'], '2099-10-03');
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $source = $pilot['sources'][0];
    $binding = new SourceBindingService;
    $draft = $binding->draft($office, $scope, $source['kind'], $source['identity'], 'Exact source binding.', (string) Str::uuid());
    $binding->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed exact binding.', (string) Str::uuid());
    $selection = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = careAnalyse($office, $scope, DB::table('wald_import_runs')->where('uuid', $selection['run'])->firstOrFail());
    $stage = DB::table('wald_import_stages')->where('id', $run->stage_id)->firstOrFail();
    expect((int) $stage->blocked_count)->toBe(1);
    $preview = (new ImportReview)->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    expect($preview['blockers'])->toContain('BLOCKED_STAGED_RECORDS');
});
