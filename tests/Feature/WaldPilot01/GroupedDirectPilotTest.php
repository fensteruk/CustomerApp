<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\GroupedPlotObservations;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
});

function groupedDirectPilotUpload(int $callCount = 1, bool $missingFirstCallNumber = false, bool $repeatCallNumber = false): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'wald-direct-master-');
    $book = new Spreadsheet;
    $headers = ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Arrival Date Installed', 'Call Status',
        'Completed Date', 'Completed Date Date', 'CAS', 'FLU', 'VS', 'TT', 'BAY', 'PFD', 'PSU', 'PSG',
        'CDF', 'CDG', 'GLS', 'PSP', 'BF', 'ALI', 'AOV', 'FI', 'WP', 'MISC', 'Customer Number', 'Site Number'];
    foreach ($headers as $index => $header) {
        $book->getActiveSheet()->setCellValue([$index + 1, 1], $header);
    }
    $book->getActiveSheet()->fromArray(['Site A - Customer A', 'Customer A - Site A - Plot 001'], null, 'A2');
    for ($index = 0; $index < $callCount; $index++) {
        $row = 3 + $index;
        if (! ($missingFirstCallNumber && $index === 0)) {
            $book->getActiveSheet()->setCellValue('A'.$row, $repeatCallNumber ? 100 : 100 + $index);
        }
        $book->getActiveSheet()->setCellValue('B'.$row, 'PC1');
        $book->getActiveSheet()->setCellValue('Z'.$row, 'C001');
        $book->getActiveSheet()->setCellValue('AA'.$row, '104-001');
    }
    $secondParent = 3 + $callCount;
    $book->getActiveSheet()->fromArray(['Site A - Customer A', 'Customer A - Site A - Plot 002'], null, 'A'.$secondParent);
    $baseRow = $secondParent + 1;
    $book->getActiveSheet()->setCellValue('E'.$baseRow, 'No');
    $book->getActiveSheet()->setCellValue('AA'.$baseRow, 'C001-002');
    (new Xls($book))->save($path);
    $book->disconnectWorksheets();

    return new UploadedFile($path, 'grouped-master.xls', 'application/vnd.ms-excel', null, true);
}

function groupedDirectPilotOffice(): User
{
    return User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
}

function groupedDirectBind(User $office, array $source, Site $site): void
{
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $service = new SourceBindingService;
    $draft = $service->draft($office, $scope, $source['kind'], $source['identity'], 'Synthetic grouped-profile binding.', (string) Str::uuid());
    $service->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Synthetic grouped-profile binding.', (string) Str::uuid());
}

function groupedDirectAnalyse(User $office, KnowledgeScope $scope, object $run): void
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
            if ($question['state'] !== 'ANSWERED' && $question['evidence']['type'] === 'STRUCTURAL'
                && count($question['evidence']['candidates']) === 1) {
                (new AnswerClarification)->handle($office, $scope, $context->uuid, $question['uuid'], $question['sequence'],
                    $question['evidence']['candidates'][0]['id'], 'Reviewed exact grouped RedZebra column.', (string) Str::uuid());
            }
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    }
}

it('analyses native grouped RedZebra rows without applying customer or plot data', function (): void {
    $office = groupedDirectPilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, groupedDirectPilotUpload(), new ExportOrder('2099-10-23', 'MORNING'),
        ExportOrder::CONFIRMATION, (string) Str::uuid());

    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    try {
        expect($pilot['mode'])->toBe('PILOT_SINGLE_SITE_SELECTION')
            ->and($pilot['manifest']['schema'])->toBe('customerapp.wald-pilot-discovery.v9')
            ->and($pilot['manifest']['source_profile'])->toBe('customerapp.redzebra-grouped-native.v1')
            ->and($pilot['sources'])->toHaveCount(1)
            ->and($pilot['sources'][0]['customer_code'])->toBe('C001')
            ->and($pilot['sources'][0]['rows'])->toBe(4)
            ->and($pilot['sources'][0]['calloff_rows'])->toBe(1)
            ->and($pilot['sources'][0]['inventory_rows'])->toBe(1)
            ->and($pilot['sources'][0]['plot_count'])->toBe(2)
            ->and($pilot['sources'][0]['plots_with_calloffs'])->toBe(1)
            ->and($pilot['sources'][0]['inventory_only_plots'])->toBe(1)
            ->and($pilot['manifest']['sources'][0]['plots'])->toBe(['001', '002']);

        $customer = CustomerOrganisation::factory()->create(['name' => 'Customer A']);
        $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Site A', 'is_active' => true]);
        $source = $pilot['sources'][0];
        groupedDirectBind($office, $source, $site);
        $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        groupedDirectAnalyse($office, $selected['scope'], $run);
        $stage = DB::table('wald_import_stages')->where('run_id', $run->id)->firstOrFail();
        $staged = DB::table('wald_staged_rows')->where('stage_id', $stage->id)->orderBy('ordinal')->pluck('payload')
            ->map(fn (string $payload): array => json_decode($payload, true, flags: JSON_THROW_ON_ERROR))->all();
        $plot001 = array_values(array_filter($staged, fn (array $row): bool => $row['facts']['plot'] === '001'));
        $plot002 = array_values(array_filter($staged, fn (array $row): bool => $row['facts']['plot'] === '002'));

        expect($staged)->toHaveCount(2)
            ->and($plot001)->toHaveCount(1)
            ->and($plot001[0]['facts']['call_number'])->toBe('100')
            ->and($plot001[0]['facts']['service'])->toBe('windows')
            ->and($plot002)->toHaveCount(1)
            ->and($plot002[0]['facts']['call_number'])->toBeNull()
            ->and($plot002[0]['facts']['service'])->toBeNull()
            ->and(DB::table('projected_plots')->count())->toBe(0)
            ->and(DB::table('wald_source_visits')->count())->toBe(0)
            ->and(DB::table('wald_import_receipts')->count())->toBe(0);

        $currentRun = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $review = new ImportReview;
        $preview = $review->preview($office, $selected['scope'], $currentRun->uuid, (int) $currentRun->epoch, (string) Str::uuid());
        expect(fn () => $review->approve($office, $selected['scope'], $currentRun->uuid, $preview['preview'],
            $preview['hash'], (string) Str::uuid()))->toThrow(ImportConflict::class, 'grouped_apply_not_enabled');
        expect(fn () => $review->commit($office, $selected['scope'], $currentRun->uuid, $preview['preview'],
            $preview['hash'], (string) Str::uuid()))->toThrow(ImportConflict::class, 'grouped_apply_not_enabled')
            ->and(DB::table('projected_plots')->count())->toBe(0)
            ->and(DB::table('wald_source_visits')->count())->toBe(0)
            ->and(DB::table('wald_import_receipts')->count())->toBe(0);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('stages a native grouped site above the standard flat-import row cap within its pinned profile limit', function (): void {
    $office = groupedDirectPilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, groupedDirectPilotUpload(501), new ExportOrder('2099-10-24', 'MORNING'),
        ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    try {
        $customer = CustomerOrganisation::factory()->create(['name' => 'Customer A']);
        $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Site A', 'is_active' => true]);
        $source = $pilot['sources'][0];
        groupedDirectBind($office, $source, $site);
        $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        groupedDirectAnalyse($office, $selected['scope'], $run);
        $stage = DB::table('wald_import_stages')->where('run_id', $run->id)->firstOrFail();
        $manifest = json_decode($stage->manifest, true, flags: JSON_THROW_ON_ERROR);

        expect((int) $stage->row_count)->toBe(502)
            ->and($manifest['source_profile'])->toBe('customerapp.redzebra-grouped-native.v1')
            ->and($manifest['integration']['max_rows'])->toBe(1000)
            ->and(DB::table('projected_plots')->count())->toBe(0)
            ->and(DB::table('wald_import_receipts')->count())->toBe(0);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('excludes a native grouped site with no call-off rows from selection', function (): void {
    $office = groupedDirectPilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, groupedDirectPilotUpload(0), new ExportOrder('2099-10-26', 'MORNING'),
        ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    try {
        $customer = CustomerOrganisation::factory()->create(['name' => 'Customer A']);
        $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Site A', 'is_active' => true]);
        expect($pilot['sources'][0]['calloff_rows'])->toBe(0)
            ->and(fn () => $workflow->select($office, $pilot['upload'], $pilot['sources'][0]['hash'], $site->uuid, (string) Str::uuid()))
            ->toThrow(ImportConflict::class, 'zero_call_site_excluded')
            ->and(DB::table('wald_pilot_selections')->count())->toBe(0)
            ->and(DB::table('projected_plots')->count())->toBe(0);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('holds missing Call Number activity for review instead of excluding it as a proven zero-call site', function (): void {
    $office = groupedDirectPilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, groupedDirectPilotUpload(1, missingFirstCallNumber: true),
        new ExportOrder('2099-10-27', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    try {
        $customer = CustomerOrganisation::factory()->create(['name' => 'Customer A']);
        $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Site A', 'is_active' => true]);
        $source = $pilot['sources'][0];

        expect($source['calloff_rows'])->toBe(0)
            ->and($source['activity_ambiguous_rows'])->toBe(1)
            ->and($source['requires_manual_review'])->toBeTrue()
            ->and(GroupedPlotObservations::isZeroCallSource($source))->toBeFalse();

        expect(fn () => $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid()))
            ->toThrow(ImportConflict::class)
            ->and(DB::table('wald_pilot_selections')->count())->toBe(0)
            ->and(DB::table('projected_plots')->count())->toBe(0)
            ->and(DB::table('wald_import_receipts')->count())->toBe(0);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('keeps duplicate grouped Call Numbers in Analyse review and blocks approval and commit', function (): void {
    $office = groupedDirectPilotOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, groupedDirectPilotUpload(2, repeatCallNumber: true),
        new ExportOrder('2099-10-28', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    try {
        $source = $pilot['sources'][0];
        expect($source['plot_count'])->toBe(2)
            ->and($source['plots_with_calloffs'])->toBe(1)
            ->and($source['inventory_only_plots'])->toBe(1)
            ->and($source['hierarchy_issues'])->toHaveCount(1)
            ->and($source['hierarchy_issues'][0]['reason'])->toBe('GROUPED_DUPLICATE_CALL_NUMBER')
            ->and(DB::table('projected_plots')->count())->toBe(0)
            ->and(DB::table('wald_source_visits')->count())->toBe(0)
            ->and(DB::table('wald_import_receipts')->count())->toBe(0);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});
