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
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

it('applies the private small workbook to the exact bound site and shows its plots', function (): void {
    $path = (string) getenv('WALD_UX_WORKBOOK');
    if ($path === '' || ! is_file($path)) {
        $this->markTestSkipped('Set WALD_UX_WORKBOOK to the approved private small workbook.');
    }
    expect(hash_file('sha256', $path))->toBe('052b5f4b508e69c5131c995bb3c6a67e5d9faa63ec23d92131785f404e9e52d4');
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true, 'is_preview_user' => false,
    ]);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Wald UX local qualification']);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'FNA2563 local qualification', 'is_active' => true]);
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, new UploadedFile($path, 'private-small.xlsx', null, null, true),
        new ExportOrder('2026-09-22', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        expect($pilot['manifest']['record_count'])->toBe(16)
            ->and($pilot['manifest']['included_count'])->toBe(15)
            ->and($pilot['manifest']['excluded_count'])->toBe(1)
            ->and($pilot['sources'])->toHaveCount(1)
            ->and($pilot['sources'][0]['customer_code'])->toBe('FNA2563');
        $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
        $bindings = new SourceBindingService;
        $source = $pilot['sources'][0];
        $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Exact local test binding.', (string) Str::uuid());
        $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'],
            'Exact local test binding reviewed.', (string) Str::uuid());
        $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        expect($run->state)->toBe('REQUIRES_REVIEW')
            ->and(DB::table('wald_clarification_answers')->where('decision', 'AUTO_SELECTED')->count())->toBeGreaterThan(0)
            ->and(DB::table('wald_clarification_answers')->where('decision', 'AUTO_SELECTED')->where('actor_role', 'wald_automatic')->count())
            ->toBe(DB::table('wald_clarification_answers')->where('decision', 'AUTO_SELECTED')->count());
        $review = new ImportReview;
        $preview = $review->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        expect($preview['blockers'])->toBe([]);
        $review->approve($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        $receipt = $review->commit($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
        $committed = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        expect(fn () => (new ImportAnalysis)->reanalyse($office, $scope, $run->uuid, (int) $committed->epoch, (string) Str::uuid()))
            ->toThrow(ImportConflict::class, 'reanalysis_not_permitted');
        expect($receipt['counts']['seen'])->toBe(16)
            ->and($receipt['counts']['excluded'])->toBe(1)
            ->and($receipt['counts']['applied'])->toBe(15)
            ->and($receipt['plot_counts']['created'])->toBe(15)
            ->and($receipt['plot_counts']['reused'])->toBe(0)
            ->and(DB::table('projected_plots')->where('site_id', $site->id)->count())->toBe(15);
        $this->actingAs($office)->get(route('office.workspace.sites.show', [$customer->uuid, $site->uuid, 'section' => 'plots']))
            ->assertOk()->assertSee('15')->assertSee('673');
        $this->actingAs($office)->get(route('office.workspace.sites.show', [$customer->uuid, $site->uuid, 'section' => 'imports']))
            ->assertOk()->assertSee('Imported — applied to CustomerApp')->assertSee('View plots');
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});

it('keeps plot choice open when Plot Ref and Plot number disagree', function (): void {
    $path = (string) getenv('WALD_UX_WORKBOOK');
    if ($path === '' || ! is_file($path)) {
        $this->markTestSkipped('Set WALD_UX_WORKBOOK to the approved private small workbook.');
    }
    expect(hash_file('sha256', $path))->toBe('052b5f4b508e69c5131c995bb3c6a67e5d9faa63ec23d92131785f404e9e52d4');
    $book = IOFactory::load($path);
    $sheet = $book->getActiveSheet();
    $cells = $sheet->toArray(null, true, true, true);
    $headerRow = null;
    $numberColumn = null;
    foreach ($cells as $rowIndex => $row) {
        foreach ($row as $column => $value) {
            if (trim((string) $value) === 'Plot number') {
                $headerRow = $rowIndex;
                $numberColumn = $column;
                break 2;
            }
        }
    }
    expect($headerRow)->not->toBeNull();
    $sheet->setCellValue($numberColumn.($headerRow + 2), '999999');
    $modified = storage_path('app/wald-ux-mismatch-'.Str::uuid().'.xlsx');
    (new Xlsx($book))->save($modified);

    try {
        config(['wald_import.pilot_available' => true]);
        DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
        $office = User::factory()->create([
            'customer_organisation_id' => null,
            'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
            'is_active' => true, 'is_preview_user' => false,
        ]);
        $customer = CustomerOrganisation::factory()->create();
        $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
        $pilot = (new PilotImportWorkflow)->upload($office, new UploadedFile($modified, 'mismatch.xlsx', null, null, true),
            new ExportOrder('2026-09-23', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
        $source = $pilot['sources'][0];
        $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
        $bindings = new SourceBindingService;
        $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Exact local test binding.', (string) Str::uuid());
        $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'],
            'Exact local test binding reviewed.', (string) Str::uuid());
        $selected = (new PilotImportWorkflow)->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        try {
            (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        } catch (ImportConflict $exception) {
            expect($exception->getMessage())->toBe('structural_clarification_required');
        }
        expect(DB::table('wald_import_runs')->where('id', $run->id)->value('state'))->toBe('NEEDS_CLARIFICATION')
            ->and(DB::table('wald_clarifications')->where('question_key', 'structure:plot_reference')->value('state'))->toBe('OPEN')
            ->and(DB::table('projected_plots')->count())->toBe(0);
    } finally {
        @unlink($modified);
        if (isset($pilot)) {
            $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->first();
            if ($upload) {
                Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
            }
        }
    }
});
