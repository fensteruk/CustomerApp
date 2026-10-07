<?php

use App\Models\PortalRole;
use App\Models\User;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\RedZebraExportProfile;
use App\SourceImport\Integration\WaldDryRun;
use App\SourceImport\Knowledge\Compatibility;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function contract02Workbook(array $rows, ?array $headers = null): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([$headers ?? ['Call', 'Call Type', 'Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer'], ...$rows]);
    $path = tempnam(sys_get_temp_dir(), 'wald-contract02-');
    try {
        (new Xlsx($book))->save($path);

        return UploadedFile::fake()->createWithContent('fictional.xlsx', file_get_contents($path));
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
}

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->office = User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
});

it('analyses the approved exact XLSX profile without completion or business mutations', function (): void {
    $tables = ['customer_organisations', 'sites', 'projected_plots', 'wald_source_bindings', 'call_off_requests',
        'projected_plot_products', 'projected_plot_services', 'wald_pilot_uploads', 'wald_import_runs'];
    $before = array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
    $report = (new WaldDryRun)->analyse($this->office, contract02Workbook([
        [1901, 'PC1', 'Fictional - Example - Plot 033', 'Different descriptive name', 46000, 46001, 'EXAMPLE'],
        [1902, 'CC1', 'Fictional - Example - Plot Com 4', 'Different descriptive name', 46002, null, 'EXAMPLE'],
    ]));
    expect($report['content']['summary'])->toMatchArray(['total_rows' => 2, 'included' => 2, 'source_units' => 1,
        'new_customer_site_proposals' => 1, 'plots_create' => 2, 'blocking_units' => 0])
        ->and($report['content']['units'][0]['customer'])->toBe('Fictional')
        ->and($report['content']['units'][0]['site'])->toBe('Example')
        ->and($report['content']['apply_approval'])->toBeFalse()
        ->and(array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables))->toBe($before);
});

it('keeps exact duplicate and date-variant Call rows blocked without deduplication', function (int $secondDate): void {
    $report = (new WaldDryRun)->analyse($this->office, contract02Workbook([
        [2001, 'PC1', 'Fictional - Example - Plot 1', 'Description', 46000, null, 'EXAMPLE'],
        [2001, 'PC1', 'Fictional - Example - Plot 1', 'Description', $secondDate, null, 'EXAMPLE'],
    ]));
    expect($report['content']['summary'])->toMatchArray(['repeated_call_groups' => 1, 'call_blocker_rows' => 2, 'blocking_units' => 1])
        ->and($report['content']['call_findings'][0]['rows'])->toHaveCount(2)
        ->and($report['content']['apply_approval'])->toBeFalse();
    expect(DB::table('wald_pilot_uploads')->count())->toBe(0)->and(DB::table('projected_plots')->count())->toBe(0);
})->with([46000, 46001]);

it('does not reinterpret Customer or Site Name outside the exact source profile', function (): void {
    $dictionary = new CustomerAppDictionary;
    expect($dictionary->field('Customer')->value)->toBeNull()
        ->and($dictionary->field('Call')->value)->toBeNull()
        ->and($dictionary->field('Site Name')->value)->toBe('transitional_site_clue');
    expect(fn () => (new WaldDryRun)->analyse($this->office, contract02Workbook([
        [2101, 'PC1', 'Fictional - Example - Plot 1', 'Description', 46000, null, 'EXAMPLE', 'EXTRA'],
    ], ['Call', 'Call Type', 'Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer', 'Extra'])))->toThrow(ImportConflict::class, 'required_call_column_unresolved');
});

it('refuses duplicate or competing header signatures', function (): void {
    $profile = new RedZebraExportProfile;
    $labels = ['Call', 'Call Type', 'Site Name', 'Customer Name', 'Arrival', 'Complete Date', 'Customer'];
    $region = ['orientation' => 'vertical_records', 'headers' => ['range' => ['start_row' => 1, 'end_row' => 1, 'start_column' => 1, 'end_column' => 7],
        'paths' => array_map(fn ($label, $column) => ['column' => $column, 'label' => $label, 'parts' => [$label]], $labels, range(1, 7))]];
    expect($profile->recognise($region, 'xlsx'))->toBe(RedZebraExportProfile::ALTERNATE)
        ->and($profile->recognise($region, 'csv'))->toBeNull();
    $region['headers']['paths'][6]['label'] = 'Customer Name';
    expect($profile->recognise($region, 'xlsx'))->toBeNull();
});

it('retains malformed hierarchy as a blocker and applies confirmed CustomerCode exclusions', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, contract02Workbook([
        [2201, 'PC1', 'Not a safe hierarchy', 'Description', 46000, null, 'EXAMPLE'],
        [2202, 'PC1', 'TEST SITE ? PLOT 9999', 'Private test description', 46000, null, 'XXTEST'],
    ]));
    expect($report['content']['summary'])->toMatchArray(['total_rows' => 2, 'included' => 1, 'excluded' => 1,
        'source_units' => 1, 'malformed_groups' => 1, 'blocking_units' => 1]);
    expect($report['content']['units'][0]['issues'])->toContain('MALFORMED_HIERARCHY');
});

it('makes pre-profile interpretation pins stale without changing the business dictionary', function (): void {
    $identity = new KnowledgeIdentity;
    $old = $identity->current();
    unset($old['source_profiles']);
    $old['policy'] = 'customerapp.wald-knowledge-policy.v3';
    expect($identity->compatible($old))->toBe(Compatibility::Stale)
        ->and($identity->current()['source_profiles'])->toBe(RedZebraExportProfile::VERSION);
});
