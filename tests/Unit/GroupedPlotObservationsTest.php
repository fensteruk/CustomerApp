<?php

use App\SourceImport\Integration\GroupedPlotObservations;
use App\SourceImport\Integration\ScopedCallEvidence;
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
        ->and($rows[2]['call_type'])->toBeNull()
        ->and((new ScopedCallEvidence)->discover($sheet, groupedDemoTable($sheet), ['call_reference' => 1], $rows))->toBe([]);
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
        ->and($profile->eligibility($rows, []))->toBe([])
        ->and((new ScopedCallEvidence)->discover($sheet, groupedDemoTable($sheet), ['call_reference' => 1], $rows))->toBe([]);
});

it('isolates repeated calls globally while retaining parent inventory and unqualified site review', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => 'Heading', 2 => 'Customer - Site - Plot 1'],
        3 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-1'],
        4 => [1 => 'Heading', 2 => 'Customer - Site - Plot 2'], 5 => [1 => '1001', 2 => 'PC1', 27 => 'CODE-2']]);
    $profile = new GroupedPlotObservations;
    $rows = $profile->classify($sheet, groupedDemoTable($sheet));
    $calls = (new ScopedCallEvidence)->discover($sheet, groupedDemoTable($sheet), ['call_reference' => 1], $rows);
    expect($calls)->toHaveCount(1)->and($calls[1001]['rows'])->toHaveCount(2)
        ->and($profile->eligibility($rows, $calls))->toBe(['CODE' => 'REVIEW'])
        ->and($rows[4]['plot_reference'])->toBe('Customer - Site - Plot 2');
});

it('does not activate the grouped path for an ordinary flat export', function (): void {
    $sheet = groupedDemoSheet([2 => [1 => '1001', 2 => 'Site', 3 => '1', 4 => 'PC1', 27 => 'CODE']]);
    expect((new GroupedPlotObservations)->matches($sheet, groupedDemoTable($sheet)))->toBeFalse();
});
