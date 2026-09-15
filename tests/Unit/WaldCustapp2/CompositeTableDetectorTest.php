<?php

use App\SourceImport\Integration\CompositeTableDetector;
use App\SourceImport\Semantics\Dictionary\CallReferenceHeader;
use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;

function custapp2Sheet(array $options = []): SheetObservation
{
    $headers = $options['headers'] ?? [3 => 'Call No.', 4 => 'Site Name', 5 => 'Plot Ref', 9 => 'Call Type', 11 => 'Completed', 20 => 'VS'];
    $headerRow = $options['header_row'] ?? 2;
    $dataStart = $options['data_start'] ?? 4;
    $dataEnd = $options['data_end'] ?? 6;
    $cells = [];
    foreach ($headers as $column => $value) {
        $cells[$headerRow][$column] = new CellObservation($headerRow, $column, $value);
    }
    foreach (range($dataStart, $dataEnd) as $row) {
        foreach (($options['row'] ?? [3 => (string) (1000 + $row), 4 => 'Synthetic Site', 5 => (string) $row, 9 => 'PC1', 11 => 'No', 20 => '2']) as $column => $value) {
            if ($value !== null) {
                $cells[$row][$column] = new CellObservation($row, $column, $value);
            }
        }
    }
    foreach ($options['extra'] ?? [] as $row => $columns) {
        foreach ($columns as $column => $value) {
            $cells[$row][$column] = new CellObservation($row, $column, $value);
        }
    }
    ksort($cells);

    return new SheetObservation(
        'sheet-1',
        'Data',
        1,
        $options['visibility'] ?? 'visible',
        null,
        $cells,
        $options['merges'] ?? [],
        $options['hidden_rows'] ?? [],
        $options['hidden_columns'] ?? [],
    );
}

it('recognises only approved CallNo semantic token pairs', function (string $header, bool $expected) {
    expect(CallReferenceHeader::matches($header))->toBe($expected);
})->with([
    ['Call No.', true], ['Call No', true], ['CallNo', true], ['CAllNo', true],
    ['CALL-NUMBER', true], ['call_number', true], ['No Call', true], ['NumberCall', true],
    ['Call Date Number', false], ['Phone Number', false], ['Number of Calls', false],
    ['Calls', false], ['No', false], ['Number', false], ['Telephone Number', false],
]);

it('recognises one deterministic complementary logical table', function () {
    $tables = (new CompositeTableDetector)->detect(custapp2Sheet());

    expect($tables)->toHaveCount(1)
        ->and($tables[0]['canonical_reference'])->toBe('C2:E2+I2:T2')
        ->and($tables[0]['data_reference'])->toBe('C4:E6+I4:T6');
});

it('handles safe structural variants and rejects unsafe or non-complementary layouts', function (array $options, int $expected) {
    expect((new CompositeTableDetector)->detect(custapp2Sheet($options)))->toHaveCount($expected);
})->with([
    'format-only spacer row' => [['extra' => [3 => [3 => '']]], 1],
    'header and data moved together' => [['header_row' => 3, 'data_start' => 5, 'data_end' => 7], 1],
    'blank call type rows remain aligned' => [['row' => [3 => '1001', 4 => 'Synthetic Site', 5 => '1', 9 => null, 11 => null, 20 => '2']], 1],
    'identity fragment may follow visit fragment' => [[
        'headers' => [2 => 'Call Type', 3 => 'Completed', 8 => 'Call No.', 9 => 'Site Name', 10 => 'Plot Ref'],
        'row' => [2 => 'PC1', 3 => 'No', 8 => '1001', 9 => 'Synthetic Site', 10 => '1'],
    ], 1],
    'internal spacer columns stay inside a fragment' => [[
        'headers' => [3 => 'CallNo', 4 => 'Site Name', 5 => 'Plot Ref', 9 => 'Call Type', 11 => 'Completed', 20 => 'VS', 28 => 'BF'],
    ], 1],
    'contiguous table does not need composition' => [[
        'headers' => [3 => 'Call No.', 4 => 'Site Name', 5 => 'Plot Ref', 6 => 'Call Type', 7 => 'Completed'],
        'row' => [3 => '1001', 4 => 'Synthetic Site', 5 => '1', 6 => 'PC1', 7 => 'No'],
    ], 0],
    'independent complete tables are not composed' => [[
        'headers' => [2 => 'Call No.', 3 => 'Site Name', 4 => 'Plot Ref', 5 => 'Call Type', 6 => 'Completed', 10 => 'Call Number', 11 => 'Site Name', 12 => 'Plot Ref', 13 => 'Call Type', 14 => 'Completed'],
        'row' => [2 => '1001', 3 => 'Synthetic Site', 4 => '1', 5 => 'PC1', 6 => 'No', 10 => '2001', 11 => 'Other Site', 12 => '2', 13 => 'CC1', 14 => 'No'],
    ], 0],
    'missing plot identity is rejected' => [['headers' => [3 => 'Call No.', 4 => 'Site Name', 9 => 'Call Type', 11 => 'Completed']], 0],
    'hidden sheet is rejected' => [['visibility' => 'hidden'], 0],
    'hidden row is rejected' => [['hidden_rows' => [5]], 0],
    'hidden column is rejected' => [['hidden_columns' => [['start' => 9, 'end' => 9]]], 0],
    'merged evidence is rejected' => [['merges' => [new SourceRange(2, 2, 3, 4)]], 0],
]);

it('returns separate candidates when two genuine composite tables compete', function () {
    $sheet = custapp2Sheet([
        'extra' => [
            10 => [3 => 'Call No.', 4 => 'Site Name', 5 => 'Plot Ref', 9 => 'Call Type', 11 => 'Completed', 20 => 'VS'],
            12 => [3 => '2001', 4 => 'Other Site', 5 => '10', 9 => 'CC1', 11 => 'No', 20 => '1'],
            13 => [3 => '2002', 4 => 'Other Site', 5 => '11', 9 => 'CC1', 11 => 'No', 20 => '1'],
        ],
    ]);

    expect((new CompositeTableDetector)->detect($sheet))->toHaveCount(2);
});
