<?php

use App\Models\PortalRole;
use App\Models\User;
use App\SourceImport\Integration\CompositePlotHierarchy;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\PairedWorkbookAnalyse;
use App\SourceImport\Integration\PairedWorkbookAnalysePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => false, 'wald_import.paired_analyse_available' => true]);
});

function pairedXlsUpload(array $rows, string $name): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'wald-paired-');
    $book = new Spreadsheet;
    foreach ($rows as $row => $cells) {
        foreach ($cells as $column => $value) {
            $book->getActiveSheet()->setCellValue([$column, $row], $value);
        }
    }
    (new Xls($book))->save($path);
    $book->disconnectWorksheets();

    return new UploadedFile($path, $name, 'application/vnd.ms-excel', null, true);
}

function pairedOffice(): User
{
    return User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
}

function pairedMasterRows(array $plots): array
{
    $headers = ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Arrival Date Installed', 'Call Status',
        'Completed Date', 'Completed Date Date', 'CAS', 'FLU', 'VS', 'TT', 'BAY', 'PFD', 'PSU', 'PSG',
        'CDF', 'CDG', 'GLS', 'PSP', 'BF', 'ALI', 'AOV', 'FI', 'WP', 'MISC', 'Customer Number', 'Site Number'];
    $rows = [1 => array_combine(range(1, 28), $headers)];
    $row = 2;
    foreach ($plots as $plot) {
        $customer = $plot['customer'] ?? 'Example Customer';
        $site = $plot['site'] ?? 'Example Site';
        $siteName = $plot['site_name'] ?? $site.' - '.$customer;
        $rows[$row++] = [1 => $siteName, 2 => $customer.' - '.$site.' - Plot '.$plot['ref']];
        $detail = [5 => 'No', 27 => $plot['code'].'-'.$plot['ref']];
        if (isset($plot['preferred_code'])) {
            $detail[26] = $plot['preferred_code'];
        }
        if (isset($plot['quantity'])) {
            $detail[11] = $plot['quantity'];
        }
        $rows[$row++] = $detail;
    }

    return $rows;
}

function pairedCallRows(array $calls): array
{
    $rows = [1 => [1 => 'Call No.', 2 => 'Customer Number', 3 => 'Site Name', 4 => 'Plot Ref', 5 => 'Call Type', 6 => 'VS']];
    foreach ($calls as $offset => $call) {
        $parsed = (new CompositePlotHierarchy)->parse($call['plot']);
        $siteName = $call['site_name'] ?? (isset($parsed['site'], $parsed['customer']) ? $parsed['site'].' - '.$parsed['customer'] : 'Example Site - Example Customer');
        $rows[$offset + 2] = [1 => $call['call'], 2 => $call['code'], 3 => $siteName,
            4 => $call['plot'], 5 => $call['type'] ?? '', 6 => $call['quantity'] ?? null];
    }

    return $rows;
}

it('reconciles full master inventory independently of Call Type and never persists imported facts', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'C001', 'ref' => '1', 'quantity' => 2],
        ['code' => 'C001', 'ref' => '2'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 101, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1', 'type' => ''],
    ]), 'calls.xls');

    $beforeStorage = glob(storage_path('app/private/wald-imports/*')) ?: [];
    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    expect($report['content']['apply_enabled'])->toBeFalse()
        ->and($report['content']['workbooks']['master']['sha256'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($report['content']['workbooks']['call_off']['sha256'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($report['content']['summary'])->toMatchArray([
            'master_source_sites' => 1,
            'qualifying_sites' => 1,
            'master_plots_on_qualifying_sites' => 2,
            'qualifying_plots_with_calloffs' => 1,
            'qualifying_plots_without_calloffs' => 1,
        ])
        ->and($report['content']['sites'][0]['plots'][1]['record_type'])->toBe('BASE_PLOT_ROW')
        ->and(DB::table('wald_pilot_uploads')->count())->toBe(0)
        ->and(DB::table('wald_source_bindings')->count())->toBe(0)
        ->and(DB::table('projected_plots')->count())->toBe(0);
    expect(glob(storage_path('app/private/wald-imports/*')) ?: [])->toBe($beforeStorage);
});

it('counts one master plot heading once when it has several detail rows', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload([
        1 => array_combine(range(1, 28), ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Arrival Date Installed',
            'Call Status', 'Completed Date', 'Completed Date Date', 'CAS', 'FLU', 'VS', 'TT', 'BAY', 'PFD', 'PSU', 'PSG',
            'CDF', 'CDG', 'GLS', 'PSP', 'BF', 'ALI', 'AOV', 'FI', 'WP', 'MISC', 'Customer Number', 'Site Number']),
        2 => [1 => 'Example Site - Example Customer', 2 => 'Example Customer - Example Site - Plot 1'],
        3 => [1 => 101, 2 => 'PC1', 27 => 'C001-1'],
        4 => [1 => 102, 2 => 'PC1', 27 => 'C001-1'],
    ], 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 201, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1'],
        ['call' => 202, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', 'C001');

    expect($site['status'])->toBe('QUALIFYING')
        ->and($site['master_plot_count'])->toBe(1)
        ->and($site['plots_with_calloffs'])->toBe(1)
        ->and($site['call_count'])->toBe(2);
});

it('holds a CustomerCode when a valid Call No has malformed hierarchy or conflicting hierarchy evidence', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'C001', 'ref' => '1'],
        ['code' => 'C001', 'ref' => '2'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 101, 'code' => 'C001', 'plot' => 'not a validated hierarchy'],
    ]), 'calls.xls');
    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $held = collect($report['content']['sites'])->firstWhere('customer_code', 'C001');
    expect($held['status'])->toBe('REVIEW')
        ->and($report['content']['summary']['qualifying_sites'])->toBe(0)
        ->and($report['content']['summary']['sites_held_for_review'])->toBe(1);

    $master2 = pairedXlsUpload(pairedMasterRows([
        ['code' => 'C001', 'ref' => '1'],
        ['code' => 'C001', 'ref' => '2'],
    ]), 'master.xls');
    $calls2 = pairedXlsUpload(pairedCallRows([
        ['call' => 101, 'code' => 'C001', 'plot' => 'Different Customer - Different Site - Plot 1'],
        ['call' => 102, 'code' => 'C001', 'plot' => 'Another Customer - Another Site - Plot 2'],
    ]), 'calls.xls');
    $conflict = (new PairedWorkbookAnalyse)->analyse($office, $master2, $calls2);
    $heldConflict = collect($conflict['content']['sites'])->firstWhere('customer_code', 'C001');
    expect($heldConflict['status'])->toBe('REVIEW')
        ->and(isset($heldConflict['issues']['hierarchy_conflict']))->toBeTrue();
});

it('holds unmatched call-off plots individually and keeps master-only sites out of the inventory', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'C001', 'ref' => '1'],
        ['code' => 'C002', 'ref' => '9', 'customer' => 'Other Customer', 'site' => 'Other Site'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 101, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1'],
        ['call' => 102, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 77'],
    ]), 'calls.xls');
    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', 'C001');
    $unusedSite = collect($report['content']['sites'])->firstWhere('master_site_reference', 'C002');
    expect($site['status'])->toBe('QUALIFYING')
        ->and($site['call_only_plots'][0]['plot_ref'])->toBe('77')
        ->and($unusedSite['status'])->toBe('ZERO_CALLOFFS_EXCLUDED')
        ->and($report['content']['summary']['master_plots_on_qualifying_sites'])->toBe(1)
        ->and($report['content']['summary']['call_only_plots_for_review'])->toBe(1);
});

it('holds a call-off-only site for review without inventing inventory', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'C001', 'ref' => '1'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 101, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1'],
        ['call' => 201, 'code' => 'C999', 'plot' => 'Other Customer - Other Site - Plot 9'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $unexpected = collect($report['content']['sites'])->firstWhere('customer_code', 'C999');
    expect($unexpected['status'])->toBe('REVIEW')
        ->and($unexpected['plots'])->toBe([])
        ->and($report['content']['summary']['call_off_only_sites_held_for_review'])->toBe(1)
        ->and($report['content']['summary']['sites_held_for_review'])->toBe(1)
        ->and($report['content']['summary']['call_only_plots_for_review'])->toBe(1);
});

it('keeps the paired Analyse gate separate from the pilot Apply gate', function (): void {
    config(['wald_import.paired_analyse_available' => false]);
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([['code' => 'C001', 'ref' => '1']]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([]), 'calls.xls');

    expect(fn () => (new PairedWorkbookAnalyse)->analyse($office, $master, $calls))
        ->toThrow(AuthorizationException::class);
});

it('does not enable the existing pilot upload when only paired Analyse is enabled', function (): void {
    $office = pairedOffice();
    $this->actingAs($office)
        ->post(route('office.workspace.pilot-import.upload'))
        ->assertNotFound();
});

it('denies paired Analyse to inactive, preview and non-Office accounts', function (): void {
    $policy = new PairedWorkbookAnalysePolicy;
    $inactive = pairedOffice();
    $inactive->is_active = false;
    $inactive->save();
    expect(fn () => $policy->authorize($inactive))->toThrow(AuthorizationException::class);

    $preview = pairedOffice();
    $preview->is_preview_user = true;
    $preview->save();
    expect(fn () => $policy->authorize($preview))->toThrow(AuthorizationException::class);

    $external = pairedOffice();
    $external->portal_role_id = DB::table('portal_roles')->where('identifier', 'site_manager')->value('id');
    $external->save();
    expect(fn () => $policy->authorize($external))->toThrow(AuthorizationException::class);
});

it('holds a site when any master plot identity is invalid or duplicated', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'C001', 'ref' => '1'],
        ['code' => 'C001', 'ref' => '1'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 201, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', 'C001');
    expect($site['status'])->toBe('REVIEW')
        ->and($report['content']['summary']['qualifying_sites'])->toBe(0)
        ->and($report['content']['summary']['master_plots_on_qualifying_sites'])->toBe(0);
});

it('redirects safely when the workbook structure is unsupported', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload([1 => [1 => 'Wrong header'], 2 => [1 => 'No supported table']], 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([]), 'calls.xls');

    $this->actingAs($office)
        ->from(route('office.workspace.imports.paired-analyse'))
        ->post(route('office.workspace.imports.paired-analyse.run'), [
            'master_register' => $master,
            'call_off_report' => $calls,
        ])
        ->assertRedirect(route('office.workspace.imports.paired-analyse'))
        ->assertSessionHasErrors('master_register');
});

it('removes both temporary source files when paired analysis stops on invalid structure', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload([1 => [1 => 'Wrong header'], 2 => [1 => 'No supported table']], 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([]), 'calls.xls');
    $beforeStorage = glob(storage_path('app/private/wald-imports/*')) ?: [];

    expect(fn () => (new PairedWorkbookAnalyse)->analyse($office, $master, $calls))
        ->toThrow(ImportConflict::class);
    expect(glob(storage_path('app/private/wald-imports/*')) ?: [])->toBe($beforeStorage);
});

it('matches the exact master Customer Number prefix to the call-off Customer Number and plot suffix', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => '104', 'ref' => '7001', 'customer' => 'Vistry', 'site' => 'Alphington'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 831, 'code' => '104', 'plot' => 'Vistry - Alphington - Plot 7001'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', '104');
    expect($site['status'])->toBe('QUALIFYING')
        ->and($site['master_site_reference'])->toBe('104')
        ->and($site['plots'][0]['plot_ref'])->toBe('7001')
        ->and($site['plots'][0]['has_calloff'])->toBeTrue();
});

it('prefers a populated master Z customer code and does not treat it as MISC product quantity', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'OLD104', 'preferred_code' => 'NEW104', 'ref' => '7001', 'customer' => 'Vistry', 'site' => 'Alphington'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 832, 'code' => 'NEW104', 'plot' => 'Vistry - Alphington - Plot 7001'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', 'NEW104');

    expect($site['status'])->toBe('QUALIFYING')
        ->and($site['master_site_reference'])->toBe('NEW104')
        ->and($report['content']['summary']['product_conflicts_for_review'])->toBe(0);
});

it('keeps exact customer-code and Plot Ref identity when descriptive Site Name labels vary', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => 'OLD104', 'preferred_code' => 'NEW104', 'ref' => '7001', 'customer' => 'Vistry', 'site' => 'Alphington'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 832, 'code' => 'NEW104', 'plot' => 'Vistry - Alphington - Plot 7001', 'site_name' => 'Alphington - Vistry'],
        ['call' => 833, 'code' => 'NEW104', 'plot' => 'Vistry - Alphington - Plot 7001', 'site_name' => 'Vistry - Alphington'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', 'NEW104');

    expect($site['status'])->toBe('QUALIFYING')
        ->and($site['call_off_source_site_names'])->toHaveCount(2)
        ->and($site['plots_with_calloffs'])->toBe(1)
        ->and($report['content']['summary']['master_plots_on_qualifying_sites'])->toBe(1);
});

it('does not fall back to names when a populated preferred code differs from the call-off code', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => '104', 'ref' => '7001', 'customer' => 'Vistry', 'site' => 'Alphington'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 833, 'code' => 'FNA104', 'plot' => 'Vistry - Alphington - Plot 7001'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);

    expect($report['content']['summary']['zero_call_sites_excluded'])->toBe(1)
        ->and(collect($report['content']['sites'])->firstWhere('customer_code', 'FNA104')['status'])->toBe('REVIEW');
});

it('retains an AA-identified plot when its descriptive master label is not parseable', function (): void {
    $office = pairedOffice();
    $rows = pairedMasterRows([
        ['code' => 'C001', 'ref' => '1', 'customer' => 'Example Customer', 'site' => 'Example Site'],
        ['code' => 'C001', 'ref' => '2', 'customer' => 'Example Customer', 'site' => 'Example Site'],
    ]);
    $rows[4][2] = 'Descriptive text with no formal plot token';
    $master = pairedXlsUpload($rows, 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 834, 'code' => 'C001', 'plot' => 'Example Customer - Example Site - Plot 1'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', 'C001');

    expect($site['status'])->toBe('QUALIFYING')
        ->and($site['master_plot_count'])->toBe(2)
        ->and($site['plots_without_calloffs'])->toBe(1);
});

it('holds an exact Customer Number when the master and call-off site hierarchies conflict', function (): void {
    $office = pairedOffice();
    $master = pairedXlsUpload(pairedMasterRows([
        ['code' => '104', 'ref' => '7001', 'customer' => 'Vistry', 'site' => 'Alphington'],
    ]), 'master.xls');
    $calls = pairedXlsUpload(pairedCallRows([
        ['call' => 831, 'code' => '104', 'plot' => 'Vistry - Another Site - Plot 7001', 'site_name' => 'Alphington - Vistry'],
    ]), 'calls.xls');

    $report = (new PairedWorkbookAnalyse)->analyse($office, $master, $calls);
    $site = collect($report['content']['sites'])->firstWhere('customer_code', '104');

    expect($site['status'])->toBe('REVIEW')
        ->and(isset($site['issues']['hierarchy_conflict']))->toBeTrue()
        ->and($report['content']['summary']['qualifying_sites'])->toBe(0);
});
