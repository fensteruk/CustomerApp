<?php

use App\Models\PortalRole;
use App\Models\User;
use App\SourceImport\Integration\GroupedPlotObservations;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\PilotWorkbookDiscovery;
use App\SourceImport\Integration\PrivateWorkbookStorage;
use App\SourceImport\Integration\WaldDryRun;
use App\SourceImport\Integration\WorkbookStager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->office = User::factory()->create(['customer_organisation_id' => null, 'is_active' => true,
        'is_preview_user' => false, 'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
});

function groupedAnalyseWorkbook(array $rows): UploadedFile
{
    if (isset($rows[2][1], $rows[2][2]) && ! ctype_digit((string) $rows[2][1]) && ! isset($rows[2][27])) {
        // A3 is the first parent in the genuine export. Keep an explicitly unclassified
        // preceding detail row rather than making the parent a competing header.
        $shifted = [2 => array_fill_keys(range(3, 26), '0')];
        foreach ($rows as $number => $values) {
            $shifted[$number + 1] = $values;
        }
        $rows = $shifted;
    }
    $headers = ['Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Arrival Date Installed', 'Call Status',
        'Completed Date', 'Completed Date Date', 'CAS', 'FLU', 'VS', 'TT', 'BAY', 'PFD', 'PSU', 'PSG',
        'CDF', 'CDG', 'GLS', 'PSP', 'BF', 'ALI', 'AOV', 'FI', 'WP', 'MISC', 'Customer Number', 'Site Number'];
    $rows = [1 => array_combine(range(1, 28), $headers)] + $rows;
    foreach ($rows as $number => &$values) {
        if ($number > 1 && isset($values[27])) {
            // Match the genuine dense detail-row shape; unused fields remain withheld.
            $values += array_fill_keys(range(3, 26), '0');
            ksort($values);
        }
    }
    unset($values);

    return new UploadedFile(WaldFixtures::xlsx([['name' => 'Grouped Fictional', 'rows' => $rows]]), 'fictional-grouped.xlsx', null, null, true);
}

function groupedAnalyseCounts(): array
{
    $counts = [];
    foreach (Schema::getTableListing() as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

it('analyses parent A3 and child roles without optional assertions or persistence and denies normal import', function (): void {
    $file = groupedAnalyseWorkbook([2 => [5 => 'Evidence', 27 => 'ORPHAN-1'],
        3 => [1 => 'Fictional heading', 2 => 'Fictional - Grouped Site - Plot 033'],
        4 => [1 => '1001', 2 => 'PC1', 3 => '45200', 4 => 'COMP', 5 => 'Yes', 9 => 'unapproved quantity', 27 => '000139-033']]);
    $before = groupedAnalyseCounts();
    $report = (new WaldDryRun)->analyse($this->office, $file);
    expect($report['content']['source_profile'])->toBe(GroupedPlotObservations::PROFILE)
        ->and($report['content']['apply_approval'])->toBeFalse()
        ->and($report['content']['withheld_source_facts'])->toBe(['products', 'completion', 'operational_dates'])
        ->and($report['content']['summary'])->toMatchArray(['plots_discovered' => 1, 'recognised_calloff_rows' => 1,
            'eligible_sites' => 1, 'site_eligibility_review' => 1, 'malformed_groups' => 1])
        ->and(groupedAnalyseCounts())->toBe($before);
    $unit = collect($report['content']['units'])->firstWhere('customer_code', '000139');
    expect($unit['issues'])->toBe([])->and($unit['example_plots'])->toContain('033');
    $storage = new PrivateWorkbookStorage;
    $artifact = $storage->store($file);
    try {
        $inspection = (new WorkbookStager)->inspect((object) $artifact);
        expect(fn () => (new PilotWorkbookDiscovery)->inspect((object) $artifact, inspection: $inspection))
            ->toThrow(ImportConflict::class, 'grouped_layout_analyse_only');
    } finally {
        $storage->discardUnregistered($artifact['storage_key']);
    }
    expect(groupedAnalyseCounts())->toBe($before);
    $this->actingAs($this->office)->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => $file])
        ->assertOk()->assertSee('Grouped workbook — analysis only')->assertSee('cannot enter normal import or Apply')
        ->assertSee('Product quantities, completion and operational dates are withheld');
    expect(groupedAnalyseCounts())->toBe($before);
});

it('retains all valid Plots on a qualifying site and excludes zero sites and category children', function (): void {
    $file = groupedAnalyseWorkbook([2 => [1 => 'Heading', 2 => 'Fictional - Active Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'ACTIVE-1'], 4 => [1 => '1002', 2 => 'CC1', 27 => 'ACTIVE-1'],
        5 => [1 => 'Heading', 2 => 'Fictional - Active Site - Plot 2'], 6 => [27 => 'ACTIVE-2'],
        7 => [1 => 'Heading', 2 => 'Fictional - Zero Site - Plot 3'], 8 => [27 => 'ZERO-3'],
        9 => [1 => 'Private One Off', 2 => 'Private - Site - Plot 4'], 10 => [1 => '1001', 2 => 'PC1', 27 => 'PRIVATE-4'],
        11 => [1 => 'Trade Supply Only', 2 => 'Trade - Site - Plot 5'], 12 => [1 => '1001', 2 => 'PC1', 27 => 'TRADE-5']]);
    $report = (new WaldDryRun)->analyse($this->office, $file);
    expect($report['content']['summary'])->toMatchArray(['plots_discovered' => 2, 'recognised_calloff_rows' => 2,
        'source_sites_discovered' => 2, 'eligible_sites' => 1, 'zero_calloff_sites' => 1,
        'source_units' => 1, 'excluded' => 6, 'plots_without_source_calloff_in_export' => 1])
        ->and($report['content']['call_findings'])->toBe([])
        ->and(DB::table('projected_plots')->count())->toBe(0);
});

it('keeps repeated calls in review without discarding independent parent inventory', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, groupedAnalyseWorkbook([
        2 => [1 => 'Heading', 2 => 'Fictional - Site - Plot 1'], 3 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-1'],
        4 => [1 => 'Heading', 2 => 'Fictional - Site - Plot 2'], 5 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-2'],
    ]));
    expect($report['content']['summary'])->toMatchArray(['plots_discovered' => 2,
        'repeated_call_groups' => 1, 'call_blocker_rows' => 2, 'eligible_sites' => 0, 'site_eligibility_review' => 1])
        ->and($report['content']['units'][0]['issues'])->toContain('DUPLICATE_CALL_NUMBER', 'SITE_ELIGIBILITY_REVIEW');
});

it('does not project a foreign-code child under the preceding site or Plot', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, groupedAnalyseWorkbook([
        2 => [1 => 'Heading', 2 => 'Fictional - First Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'FIRST-1'], 4 => [27 => 'SECOND-2'],
    ]));
    $foreign = collect($report['content']['units'])->firstWhere('customer_code', 'SECOND');
    expect($foreign['plot_count'])->toBe(0)->and($foreign['issues'])->toContain('GROUPED_PARENT_SITE_MISMATCH')
        ->and($report['content']['summary']['plots_discovered'])->toBe(1);
});

it('reports a wholly excluded zero-call site without manufacturing a candidate', function (): void {
    $report = (new WaldDryRun)->analyse($this->office, groupedAnalyseWorkbook([
        2 => [1 => 'Heading', 2 => 'Fictional - Zero Site - Plot 1'], 3 => [27 => 'ZERO-1'],
    ]));
    expect($report['content']['summary'])->toMatchArray(['plots_discovered' => 0,
        'source_units' => 0, 'excluded' => 2, 'zero_calloff_sites' => 1])
        ->and($report['content']['apply_approval'])->toBeFalse();
});
