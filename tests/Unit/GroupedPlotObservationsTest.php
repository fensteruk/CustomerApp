<?php

use App\SourceImport\Integration\CompositePlotHierarchy;
use App\SourceImport\Integration\GroupedPlotObservations;
use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\SheetObservation;

function groupedDemoSheet(array $rows): SheetObservation
{
    $rows = [1 => [1 => 'Call No.', 2 => 'Site Name', 3 => 'Plot Ref', 4 => 'Call Type',
        27 => 'Customer Number', 28 => 'Site Number']] + $rows;
    $cells = [];
    foreach ($rows as $row => $values) {
        foreach ($values as $column => $value) {
            $cells[$row][$column] = new CellObservation($row, $column, (string) $value);
        }
    }

    return new SheetObservation('sheet-1', 'Fictional', 1, 'visible', 'A1:AB'.max(array_keys($rows)), $cells);
}

function groupedDemoTable(SheetObservation $sheet): array
{
    return ['header_range' => ['start_row' => 1, 'end_row' => 1],
        'data_start_row' => 2, 'data_end_row' => max(array_keys($sheet->cells))];
}

it('classifies the parent heading before call validation and retains physical provenance', function (): void {
    $sheet = groupedDemoSheet([3 => [1 => 'Fictional heading', 2 => 'Customer - Site - Plot 033'],
        4 => [1 => '1001', 2 => 'PC1', 27 => '000139-opaque-suffix']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));
    expect($profile->matches($sheet, groupedDemoTable($sheet)))->toBeTrue()
        ->and($rows[3]['call_reference'])->toBeNull()
        ->and($rows[3]['source_customer_code'])->toBe('000139')
        ->and($rows[4]['call_type'])->toBe('PC1')
        ->and($rows[4]['provenance']['plot_cell'])->toBe('B3')
        ->and($rows[3]['provenance']['identity_cell'])->toBe('AA4')
        ->and($sheet->cells[3][1]->rawValue)->toBe('Fictional heading')
        ->and($sheet->cells[4][27]->rawValue)->toBe('000139-opaque-suffix');
});

it('associates multiple calls with the same parent Plot without making parent calls', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-1'], 4 => [1 => '1002', 2 => 'CC1', 27 => 'CODE-1']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));
    expect($rows[3]['plot_reference'])->toBe($rows[4]['plot_reference'])
        ->and($rows[3]['source_customer_code'])->toBe($rows[4]['source_customer_code'])
        ->and($rows[2]['call_type'])->toBeNull();
});

it('retains consecutive base Plots when a late qualifying call establishes whole-site eligibility', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'], 3 => [27 => 'CODE-1'],
        4 => [1 => 'Heading', 2 => 'Customer - Site - Plot 2'], 5 => [27 => 'CODE-2'],
        6 => [1 => 'Heading', 2 => 'Customer - Site - Plot 3'], 7 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-3']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));
    expect($rows[3]['issue'])->toBeNull()->and($rows[5]['issue'])->toBeNull()
        ->and($rows[3]['call_reference'])->toBeNull()->and($rows[5]['call_type'])->toBeNull()
        ->and($profile->eligibility($rows, []))->toBe(['CODE' => 'ELIGIBLE']);
});

it('keeps a parent with no detail rows unresolved instead of borrowing the next parent code', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading A', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => 'Heading B', 2 => 'Customer - Site - Plot 2'], 4 => [27 => 'CODE-2']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

    expect($rows[2]['source_customer_code'])->toBeNull()
        ->and($rows[2]['issue'])->toBe('GROUPED_CUSTOMER_CODE_MISSING')
        ->and($rows[3]['source_customer_code'])->toBe('CODE')
        ->and($rows[4]['source_customer_code'])->toBe('CODE');
});

it('classifies an A/B-only opaque Call Number as a child, not a new parent heading', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => 'CALL-X9', 2 => 'PC1'], 4 => [1 => '1002', 2 => 'PC1', 27 => '104-001']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

    expect($rows[3]['kind'])->toBe('CHILD_DETAIL')
        ->and($rows[3]['provenance']['parent_row'])->toBe(2)
        ->and($rows[3]['plot_reference'])->toBe('Customer - Site - Plot 1')
        ->and($rows[3]['call_reference'])->toBe('CALL-X9');
});

it('ends parent context at a malformed hierarchy boundary and holds following details as orphans', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading A', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => '104-001'],
        4 => [1 => 'Heading B', 2 => 'Customer - Site -'],
        5 => [1 => '1002', 2 => 'PC1', 27 => '104-002']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

    expect($rows[4]['issue'])->toBe('GROUPED_PARENT_HIERARCHY_INVALID')
        ->and($rows[5]['issue'])->toBe('GROUPED_ORPHAN_ROW');
});

it('fences malformed hierarchy boundaries that use supported en and em dash separators', function (): void {
    foreach (["\u{2013}", "\u{2014}"] as $separator) {
        $sheet = groupedDemoSheet([2 => [1 => 'Heading A', 2 => 'Customer - Site - Plot 1'],
            3 => [1 => '1001', 2 => 'PC1', 27 => '104-001'],
            4 => [1 => 'Heading B', 2 => 'Customer '.$separator.' Site '.$separator],
            5 => [1 => '1002', 2 => 'PC1', 27 => '104-002']]);
        $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

        expect($rows[4]['issue'])->toBe('GROUPED_PARENT_HIERARCHY_INVALID')
            ->and($rows[5]['issue'])->toBe('GROUPED_ORPHAN_ROW');
    }
});

it('keeps identical Plot references isolated under different source site codes', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading A', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => '104-001'],
        4 => [1 => 'Heading B', 2 => 'Customer - Site - Plot 1'],
        5 => [1 => '1002', 2 => 'PC1', 27 => '105-001']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

    expect($rows[3]['plot_reference'])->toBe($rows[5]['plot_reference'])
        ->and($rows[3]['source_customer_code'])->toBe('104')
        ->and($rows[5]['source_customer_code'])->toBe('105');
});

it('keeps one CustomerCode identity when descriptive headings vary and leaves hierarchy conflict visible', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Newham Farm', 2 => 'Customer - Newham Farm - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => '104-001'],
        4 => [1 => 'Newham Farm Truro Phases 2-7', 2 => 'Customer - Newham Farm Truro Phases 2-7 - Plot 2'],
        5 => [1 => '1002', 2 => 'PC1', 27 => '104-002']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));
    $siteA = (new CompositePlotHierarchy)->parse($rows[3]['plot_reference'], $rows[3]['source_customer_code']);
    $siteB = (new CompositePlotHierarchy)->parse($rows[5]['plot_reference'], $rows[5]['source_customer_code']);

    expect($rows[3]['source_customer_code'])->toBe($rows[5]['source_customer_code'])
        ->and($siteA['site'])->not->toBe($siteB['site']);
});

it('fences a foreign site code and never resumes inherited context without a new parent', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'FIRST-1'], 4 => [27 => 'SECOND-1'], 5 => [27 => 'FIRST-1']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));
    expect($rows[3]['issue'])->toBeNull()
        ->and($rows[4]['source_customer_code'])->toBe('SECOND')
        ->and($rows[4]['issue'])->toBe('GROUPED_PARENT_SITE_MISMATCH')
        ->and($rows[5]['issue'])->toBe('GROUPED_ORPHAN_ROW');
});

it('keeps orphan and missing-identity rows unresolved without invented parent or code', function (): void {
    $sheet = groupedDemoSheet([2 => [27 => 'ORPHAN-1'],
        3 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'], 4 => [1 => '1001', 2 => 'PC1']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));
    expect($rows[2]['issue'])->toBe('GROUPED_ORPHAN_ROW')->and($rows[2]['plot_reference'])->toBeNull()
        ->and($rows[4]['source_customer_code'])->toBeNull()
        ->and($rows[4]['issue'])->toBe('GROUPED_CUSTOMER_CODE_MISSING')
        ->and($profile->eligibility($rows, []))->toBe(['ORPHAN' => 'REVIEW']);
});

it('excludes both approved categories and their children before call identity or site qualification', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Private One Off', 2 => 'Private - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'PRIVATE-1'],
        4 => [1 => 'Trade Supply Only', 2 => 'Trade - Site - Plot 2'],
        5 => [1 => '1001', 2 => 'PC1', 27 => 'TRADE-2']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));
    expect(array_column($rows, 'excluded'))->toBe([true, true, true, true])
        ->and($profile->eligibility($rows, []))->toBe([]);
});

it('isolates repeated calls globally while retaining parent inventory and unqualified site review', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-1'],
        4 => [1 => 'Heading', 2 => 'Customer - Site - Plot 2'], 5 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-2']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));
    expect($profile->eligibility($rows, [1001 => ['rows' => [3, 5]]]))->toBe(['CODE' => 'REVIEW'])
        ->and($rows[4]['plot_reference'])->toBe('Customer - Site - Plot 2');
});

it('does not activate the grouped path for an ordinary flat export', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => '1001', 2 => 'Site', 3 => '1', 4 => 'PC1', 27 => 'CODE']]);
    expect((new GroupedPlotObservations)->matches($sheet, groupedDemoTable($sheet)))->toBeFalse();
});

it('recognises inventory-only details, opaque calls, and the versioned Z-over-AA identity rule', function (): void {
    $sheet = groupedDemoSheet([
        2 => [1 => 'Site A - Customer A', 2 => 'Customer A - Site A - Plot 001'],
        3 => [27 => '104-001'],
        4 => [1 => 'Site A - Customer A', 2 => 'Customer A - Site A - Plot 002'],
        5 => [1 => 'CALL-X9', 2 => 'Q001', 26 => 'FNA2472', 27 => '216-002'],
        6 => [1 => 'Site A - Customer A', 2 => 'Customer A - Site A - Plot 003'],
        7 => [1 => 1003, 2 => 'PC1', 26 => 'FNA2472', 27 => '216-003'],
    ]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));

    expect($profile->matches($sheet, groupedDemoTable($sheet)))->toBeTrue()
        ->and(GroupedPlotObservations::NATIVE_PROFILE)->toBe('customerapp.redzebra-grouped-native.v1')
        ->and($rows[3]['kind'])->toBe('CHILD_DETAIL')
        ->and($rows[3]['call_reference'])->toBeNull()
        ->and($rows[3]['source_customer_code'])->toBe('104')
        ->and($rows[3]['provenance']['preferred_code_cell'])->toBeNull()
        ->and($rows[3]['provenance']['identity_cell'])->toBe('AA3')
        ->and($rows[3]['provenance']['source_customer_code_raw'])->toBe('104-001')
        ->and($rows[5]['call_reference'])->toBe('CALL-X9')
        ->and($rows[5]['call_type'])->toBe('Q001')
        ->and($rows[5]['source_customer_code'])->toBe('FNA2472')
        ->and($rows[5]['issue'])->toBeNull()
        ->and($rows[7]['call_reference'])->toBe('1003')
        ->and($rows[7]['call_type'])->toBe('PC1')
        ->and($rows[7]['source_customer_code'])->toBe('FNA2472')
        ->and($rows[7]['provenance']['parent_row'])->toBe(6)
        ->and($rows[7]['provenance']['plot_cell'])->toBe('B6');
});

it('falls back from an invalid preferred code to a valid legacy code and preserves the source cell value', function (): void {
    $sheet = groupedDemoSheet([
        2 => [1 => 'Site A - Customer A', 2 => 'Customer A - Site A - Plot 001'],
        3 => [26 => '<invalid>', 27 => '104-001'],
    ]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

    expect($rows[3]['source_customer_code'])->toBe('104')
        ->and($rows[3]['provenance']['preferred_code_cell'])->toBeNull()
        ->and($rows[3]['provenance']['identity_cell'])->toBe('AA3')
        ->and($rows[3]['provenance']['source_customer_code_raw'])->toBe('104-001');
});

it('holds a blank-call row with a nonblank type without treating it as a base plot', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [2 => 'PC1', 27 => 'CODE-1']]);
    $rows = (new GroupedPlotObservations)->classify($sheet, groupedDemoTable($sheet));

    expect($rows[3]['call_reference'])->toBeNull()
        ->and($rows[3]['call_type'])->toBe('PC1')
        ->and($rows[3]['issue'])->toBe('GROUPED_CALL_NUMBER_MISSING');
});

it('qualifies site activity from an opaque Call Number even when Call Type remains unknown', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => 'CALL-X9', 2 => 'Q001', 27 => 'CODE-1']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));

    expect($rows[3]['call_reference'])->toBe('CALL-X9')
        ->and($profile->eligibility($rows, []))->toBe(['CODE' => 'ELIGIBLE']);
});

it('keeps sites with only approved excluded Call Types in the zero-call class', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => 'CALL-X9', 2 => 'CU4', 27 => 'CODE-1']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));

    expect($profile->eligibility($rows, []))->toBe(['CODE' => 'ZERO_CALLOFFS']);
});
