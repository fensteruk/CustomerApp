<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlotService;
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

function optionalCompletionUpload(User $office, bool $hasCompletion, string $date): array
{
    $header = 'CustomerNo,Call No.,Site Name,Plot Ref,Call Type,'.($hasCompletion ? 'Complete,' : '').'VS';
    $row = 'CODE-1,1001,Example Site,Plot 1,PC1,'.($hasCompletion ? 'Yes,' : '').'2';

    return (new PilotImportWorkflow)->upload($office,
        UploadedFile::fake()->createWithContent('master.csv', $header."\n".$row."\n"),
        new ExportOrder($date, 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
}

function optionalCompletionAnalyse(User $office, KnowledgeScope $scope, object $run): object
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

it('accepts an absent completion column without reversing an established source completion', function (): void {
    $office = User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => true]);
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $workflow = new PilotImportWorkflow;
    $review = new ImportReview;

    $first = optionalCompletionUpload($office, true, '2099-09-22');
    $source = $first['sources'][0];
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Exact source code binding.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed exact binding.', (string) Str::uuid());

    foreach ([true, false] as $hasCompletion) {
        $upload = $hasCompletion ? $first : optionalCompletionUpload($office, false, '2099-09-23');
        $selected = $workflow->select($office, $upload['upload'], $upload['sources'][0]['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        $run = optionalCompletionAnalyse($office, $scope, $run);
        $stage = DB::table('wald_import_stages')->where('id', $run->stage_id)->firstOrFail();
        expect((int) $stage->blocked_count)->toBe(0);
        $preview = $review->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        expect($preview['blockers'])->toBe([]);
        $payload = json_decode(DB::table('wald_import_previews')->where('uuid', $preview['preview'])->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        expect($payload['projection']['changes'][0]['after_complete'])->toBeTrue();
        $review->approve($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        $review->commit($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        expect(ProjectedPlotService::query()->where('source_call_number', '1001')->firstOrFail()->isSourceCompleted())->toBeTrue();
    }

    expect(DB::table('source_projection_events')->where('event_type', 'completion_reversed')->count())->toBe(0);
});
