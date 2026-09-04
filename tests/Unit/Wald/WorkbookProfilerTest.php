<?php

use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures as Fixtures;

function waldProfiler(): WorkbookProfiler
{
    $values = new ValueProfiler;

    return new WorkbookProfiler(new WorkbookSourceFactory, new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));
}

function waldSheet(array $rows, array $options = []): array
{
    return waldProfiler()->profile(Fixtures::xlsx([['rows' => $rows, ...$options]]), 'xlsx')->toArray()['sheets'][0];
}

afterEach(fn () => Fixtures::cleanup());

it('profiles a clean table with exact physical lineage and a neutral versioned contract', function () {
    $path = Fixtures::xlsx([['name' => 'Programme', 'rows' => Fixtures::table()]]);
    $data = waldProfiler()->profile($path, 'xlsx')->toArray();
    $sheet = $data['sheets'][0];
    $region = $sheet['regions'][0];
    expect($data['source_checksum'])->toBe(hash_file('sha256', $path))
        ->and($data['schema'])->toBe('wald.workbook-profile.v1')
        ->and($data['ready_for_staging'])->toBeFalse()
        ->and($sheet['meaningful_range']['address'])->toBe('A1:C3')
        ->and($region['headers']['range']['address'])->toBe('A1:C1')
        ->and($region['orientation'])->toBe('vertical_records')
        ->and($region['column_profiles'][0]['samples'][0]['value'])->toBe('001')
        ->and($region['column_profiles'][0]['samples'][0]['source']['cell'])->toBe('A2')
        ->and($region['evidence'][0]['rule_key'])->toBe('wald.structure.region.occupancy');
});

it('trims blank margins and distinguishes report titles from headers', function () {
    $rows = [5 => [1 => 'ABC Homes Construction Programme'], 6 => [1 => 'Week Ending 2 September 2026']] + Fixtures::move(Fixtures::table(), 7);
    $sheet = waldSheet($rows, ['dimension' => 'A1:AZ520']);
    $table = array_values(array_filter($sheet['regions'], fn ($region) => $region['hypothesis'] === 'table'))[0];
    expect($sheet['meaningful_range']['address'])->toBe('A5:C10')
        ->and($sheet['blank_margins']['leading_rows'])->toBe(4)
        ->and($sheet['blank_margins']['trailing_declared_rows'])->toBe(510)
        ->and($table['headers']['range']['address'])->toBe('A8:C8');
});

it('composes two-row headers with repeated or merged parent labels', function (bool $merged) {
    $rows = [1 => $merged ? [1 => 'Planned', 3 => 'Actual'] : [1 => 'Planned', 2 => 'Planned', 3 => 'Actual', 4 => 'Actual'], 2 => [1 => 'Date', 2 => '%', 3 => 'Date', 4 => '%'], 3 => [1 => '2026-09-01', 2 => '50%', 3 => '2026-09-02', 4 => '20%']];
    $sheet = waldSheet($rows, ['merges' => $merged ? ['A1:B1', 'C1:D1'] : []]);
    $header = $sheet['regions'][0]['headers'];
    expect($header['range']['address'])->toBe('A1:D2')
        ->and(array_column($header['paths'], 'label'))->toBe(['Planned / Date', 'Planned / %', 'Actual / Date', 'Actual / %'])
        ->and($sheet['non_empty_cell_count'])->toBe($merged ? 10 : 12);
    if ($merged) {
        expect($header['paths'][1]['source_refs'][0]['cell'])->toBe('A1')
            ->and($header['paths'][1]['source_refs'][0]['merge_range'])->toBe('A1:B1');
    }
})->with([true, false]);

it('supports three-row grouped headers and keeps duplicate leaf labels distinct', function () {
    $rows = [1 => [1 => 'Plan', 3 => 'Actual'], 2 => [1 => 'Group', 3 => 'Group'], 3 => [1 => 'Date', 2 => 'Date', 3 => 'Date', 4 => 'Date'], 4 => [1 => 1, 2 => 2, 3 => 3, 4 => 4]];
    $sheet = waldSheet($rows, ['merges' => ['A1:B1', 'C1:D1', 'A2:B2', 'C2:D2']]);
    expect($sheet['regions'][0]['headers']['range']['address'])->toBe('A1:D3')
        ->and($sheet['regions'][0]['headers']['paths'][1]['column'])->toBe(2)
        ->and($sheet['regions'][0]['headers']['paths'][1]['label'])->toBe('Plan / Group / Date');
});

it('retains hidden sheets rows columns and source metadata', function () {
    $path = Fixtures::xlsx([['name' => 'Visible', 'rows' => Fixtures::table()], ['name' => 'Lookup', 'visibility' => 'veryHidden', 'hidden_rows' => [2], 'hidden_columns' => [2], 'rows' => Fixtures::table()]]);
    $profile = waldProfiler()->profile($path, 'xlsx')->toArray();
    expect($profile['sheet_count'])->toBe(2)->and($profile['hidden_sheet_count'])->toBe(1)
        ->and($profile['sheets'][1]['position'])->toBe(2)
        ->and($profile['sheets'][1]['hidden_row_count'])->toBe(1)
        ->and($profile['sheets'][1]['hidden_column_count'])->toBe(1)
        ->and($profile['sheets'][1]['non_empty_cell_count'])->toBe(9)
        ->and($profile['sheets'][1]['warnings'])->toContain('hidden_region_inclusion_required');
});

it('preserves formula expressions cache state and style evidence without calculating', function () {
    $rows = Fixtures::table();
    $rows[1][3] = ['value' => 'Amount', 'style' => 1];
    $rows[2][3] = ['formula' => '1+1', 'value' => 99, 'type' => 'n'];
    $rows[3][3] = ['formula' => 'WEBSERVICE("https://invalid.test")', 'no_cache' => true, 'type' => 'n'];
    $sheet = waldSheet($rows);
    expect($sheet['formula_count'])->toBe(2)
        ->and($sheet['formula_samples'][0]['expression'])->toBe('1+1')
        ->and($sheet['formula_samples'][0]['cached_raw_value'])->toBe('99')
        ->and($sheet['formula_samples'][0]['cached_value_freshness'])->toBe('unknown')
        ->and($sheet['formula_samples'][1]['cached_value_available'])->toBeFalse();
    $source = (new WorkbookSourceFactory)->open(Fixtures::xlsx([['rows' => $rows]]), 'xlsx', new AnalysisBudget);
    try {
        foreach ($source->sheets() as $observation) {
            expect($observation->cells[1][3]->style)->toMatchArray(['bold' => true, 'fill_id' => 2, 'border_id' => 1]);
        }
    } finally {
        $source->close();
    }
});

it('separates footers and repeated phase blocks', function () {
    $rows = [1 => [1 => 'Phase 1']] + Fixtures::move(Fixtures::table(), 1) + [5 => [1 => 'Total', 3 => ['formula' => 'SUM(C3:C4)', 'value' => 30, 'type' => 'n']], 7 => [1 => 'Phase 2']] + Fixtures::move(Fixtures::table(), 7);
    $sheet = waldSheet($rows);
    $tables = array_values(array_filter($sheet['regions'], fn ($region) => $region['hypothesis'] === 'table'));
    expect(array_column(array_column($tables, 'range'), 'address'))->toBe(['A2:C4', 'A8:C10'])
        ->and(array_column($sheet['regions'], 'hypothesis'))->toContain('footer')
        ->and(array_values($sheet['repeated_blocks']))->toBe([['sheet-1:A2:C4', 'sheet-1:A8:C10']]);
});

it('detects two tables side by side', function () {
    $rows = Fixtures::table();
    foreach (Fixtures::move(Fixtures::table(), 0, 4) as $row => $cells) {
        $rows[$row] += $cells;
    }
    $sheet = waldSheet($rows);
    expect(array_column(array_column($sheet['regions'], 'range'), 'address'))->toBe(['A1:C3', 'E1:G3']);
});

it('ignores formatting-only cells outside the meaningful range', function () {
    $rows = Fixtures::table() + [520 => [100 => ['value' => '', 'style' => 1]]];
    $sheet = waldSheet($rows, ['dimension' => 'A1:XFD1048576']);
    expect($sheet['meaningful_range']['address'])->toBe('A1:C3')
        ->and($sheet['declared_range']['address'])->toBe('A1:XFD1048576')
        ->and($sheet['non_empty_cell_count'])->toBe(9);
});

it('profiles matrix and transposed orientations', function (array $first, string $expected) {
    $sheet = waldSheet([1 => $first, 2 => [1 => 'Height', 2 => 2, 3 => 3], 3 => [1 => 'Width', 2 => 4, 3 => 5]]);
    expect($sheet['regions'][0]['orientation'])->toBe($expected);
})->with([
    [[1 => 'Metric', 2 => 'Alpha', 3 => 'Beta'], 'matrix'],
    [[1 => 'Length', 2 => 5, 3 => 6], 'transposed'],
]);

it('reads CSV through the same profile with dialect encoding and multiline lineage', function () {
    $path = Fixtures::file("\xEF\xBB\xBFReference;Description;Amount\n001;\"two\nlines\";50\nA02;\"one;field\";20\n");
    $data = waldProfiler()->profile($path, 'csv')->toArray();
    $sample = $data['sheets'][0]['regions'][0]['column_profiles'][1]['samples'][0];
    expect($data['reader']['delimiter'])->toBe(';')->and($data['reader']['encoding'])->toBe('UTF-8')
        ->and($data['sheets'][0]['meaningful_range']['address'])->toBe('A1:C3')
        ->and($sample['value'])->toBe("two\nlines")
        ->and($sample['source'])->toMatchArray(['record' => 2, 'line_start' => 2, 'line_end' => 3]);
});

it('profiles date percentage identifier and quantity observations without normalising guesses', function () {
    $rows = [1 => [1 => 'Ref', 2 => 'When', 3 => 'Value'], 2 => [1 => '001', 2 => '01/02/26', 3 => '50%'], 3 => [1 => 'PLOT-14', 2 => '31/01/2026', 3 => 50], 4 => [1 => '14A', 2 => ['value' => 46000, 'style' => 2], 3 => ['value' => 0.5, 'style' => 3]], 5 => [1 => 'A01', 2 => '2026-09-03', 3 => -2]];
    $profiles = waldSheet($rows)['regions'][0]['column_profiles'];
    expect($profiles[0]['identifier']['count'])->toBe(4)
        ->and($profiles[1]['types']['date_like'])->toBe(4)
        ->and($profiles[1]['dates']['ambiguous'])->toBeTrue()
        ->and($profiles[2]['types']['percentage_like'])->toBe(2)
        ->and($profiles[2]['numeric']['negative_count'])->toBe(1)
        ->and($profiles[2]['percentage']['normalised'])->toBeFalse()
        ->and($profiles[2]['percentage']['possible_scales'])->toContain('unconfirmed_0_to_100', 'formatted_fraction', 'explicit_percent_text');
});

it('reports ambiguous headers instead of claiming certainty', function () {
    $sheet = waldSheet([1 => [1 => 'Alpha', 2 => 'Beta'], 2 => [1 => 'Gamma', 2 => 'Delta'], 3 => [1 => 'Epsilon', 2 => 'Zeta']]);
    expect($sheet['warnings'])->toContain('ambiguous_header_candidates')
        ->and($sheet['regions'][0]['headers']['alternatives'][0]['hypothesis'])->toBe('all_rows_may_be_data');
});

it('is deterministic and fingerprints structure separately from original bytes', function () {
    $path = Fixtures::xlsx([['rows' => Fixtures::table()]]);
    $a = waldProfiler()->profile($path, 'xlsx')->toArray();
    expect(waldProfiler()->profile($path, 'xlsx')->toArray())->toBe($a);
    $rows = Fixtures::table();
    $rows[2][3] = 11;
    $b = waldProfiler()->profile(Fixtures::xlsx([['rows' => $rows]]), 'xlsx')->toArray();
    expect($a['structural_fingerprint'])->toBe($b['structural_fingerprint'])
        ->and($a['source_checksum'])->not->toBe($b['source_checksum']);
});

it('preserves sparse coordinates under deterministic mutations', function () {
    $rows = Fixtures::move(Fixtures::insertColumn(Fixtures::reorder(Fixtures::table(), [3, 1, 2]), 2), 4, 1);
    $sheet = waldSheet($rows);
    expect($sheet['meaningful_range']['address'])->toBe('B5:E7')
        ->and($sheet['regions'][0]['headers']['paths'][2]['label'])->toBe('Reference')
        ->and($sheet['regions'][0]['column_profiles'][2]['samples'][0]['source']['cell'])->toBe('D6');
});

it('keeps raw business labels without emitting semantic mappings', function () {
    $sheet = waldSheet([1 => [1 => 'Plot Number', 2 => 'House Type', 3 => 'Stage', 4 => 'Product Code', 5 => 'Call Type'], 2 => [1 => '001', 2 => 'A', 3 => 'Windows', 4 => 'PC1', 5 => 'CM1']]);
    $json = json_encode($sheet, JSON_THROW_ON_ERROR);
    expect($sheet['regions'][0]['headers']['paths'][0]['label'])->toBe('Plot Number');
    foreach (['semantic_field', 'canonical_type', 'plot_id', 'workflow_stage_id', 'dictionary_ref":{'] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }
});

it('rejects corrupt archives and unsupported legacy formats safely', function (string $format) {
    expect(fn () => waldProfiler()->profile(Fixtures::file('not a workbook'), $format))->toThrow(AnalysisProblem::class);
})->with(['xlsx', 'xls', 'xlsm', 'ods']);

it('fails safely on configured resource limits without publishing partial profiles', function (string $limit, int $maximum) {
    $path = Fixtures::xlsx([['rows' => Fixtures::table()]]);
    try {
        waldProfiler()->profile($path, 'xlsx', new AnalysisBudget([$limit => $maximum]));
        $this->fail('A partial result must never be published.');
    } catch (AnalysisProblem $problem) {
        expect($problem->problemCode)->toBe('resource_limit_exceeded')->and($problem->toArray()['complete'])->toBeFalse();
    }
})->with([['cells', 4], ['rows', 2], ['columns', 2], ['sheets', 0], ['entry_bytes', 100], ['archive_bytes', 100], ['memory_bytes', 1]]);

it('rejects macros traversal XML entities and malformed worksheet XML', function (array $parts) {
    $path = Fixtures::xlsx([['rows' => Fixtures::table()]], $parts);
    expect(fn () => waldProfiler()->profile($path, 'xlsx'))->toThrow(AnalysisProblem::class);
})->with([
    [['xl/vbaProject.bin' => 'macro']],
    [['../outside.xml' => 'bad']],
    [['xl/worksheets/sheet1.xml' => '<!DOCTYPE worksheet [<!ENTITY x SYSTEM "file:///secret">]><worksheet>&x;</worksheet>']],
    [['xl/worksheets/sheet1.xml' => '<worksheet><sheetData>']],
]);

it('records external links and comments as unavailable without fetching them', function () {
    $path = Fixtures::xlsx([['rows' => Fixtures::table()]], ['xl/worksheets/_rels/sheet1.xml.rels' => '<Relationships><Relationship Id="external" TargetMode="External" Target="https://invalid.test/private"/></Relationships>', 'xl/comments1.xml' => '<comments/>']);
    $profile = waldProfiler()->profile($path, 'xlsx')->toArray();
    expect($profile['warnings'])->toContain('external_data_not_fetched', 'comments_present_not_read')
        ->and($profile['reader']['capabilities']['comments'])->toBeFalse();
});

it('preserves rich shared strings and warns about unsupported shared formula context', function () {
    $rows = Fixtures::table();
    $rows[2][2] = ['value' => 0, 'type' => 's'];
    $rows[2][3] = ['formula' => '', 'formula_type' => 'shared', 'value' => 2, 'type' => 'n'];
    $path = Fixtures::xlsx([['rows' => $rows]], ['xl/sharedStrings.xml' => '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><r><t>Al</t></r><r><t>pha</t></r></si></sst>']);
    $sheet = waldProfiler()->profile($path, 'xlsx')->toArray()['sheets'][0];
    expect($sheet['regions'][0]['column_profiles'][1]['samples'][0]['value'])->toBe('Alpha')
        ->and($sheet['warnings'])->toContain('formula_context_required');
});

it('returns explicit diagnostics for an empty workbook', function () {
    $sheet = waldSheet([]);
    expect($sheet['meaningful_range'])->toBeNull()->and($sheet['regions'])->toBe([])
        ->and($sheet['warnings'])->toContain('no_meaningful_data', 'no_candidate_table');
});

it('recognises month year and week shapes while retaining ambiguity', function () {
    $sheet = waldSheet([1 => [1 => 'Reference', 2 => 'Period'], 2 => [1 => 'A01', 2 => '2026-09'], 3 => [1 => 'A02', 2 => '09/2026'], 4 => [1 => 'A03', 2 => 'Week 12']]);
    $dates = $sheet['regions'][0]['column_profiles'][1]['dates'];
    expect($dates['shapes'])->toBe(['month_year_shaped' => 2, 'week_shaped' => 1])
        ->and($dates['ambiguous'])->toBeTrue();
});

it('detects repeated headers without blank separating rows', function () {
    $sheet = waldSheet(Fixtures::table() + Fixtures::move(Fixtures::table(), 3));
    expect(array_column(array_column($sheet['regions'], 'range'), 'address'))->toBe(['A1:C3', 'A4:C6'])
        ->and($sheet['evidence'][1]['rule_key'])->toBe('wald.structure.region.repeated_blocks');
});

it('extends meaningful bounds only for a merge with actual content', function () {
    $sheet = waldSheet([1 => [1 => 'Report'], 3 => [1 => 'Ref', 2 => 'Value'], 4 => [1 => 'A01', 2 => 5]], ['merges' => ['A1:F1', 'A500:Z520']]);
    expect($sheet['meaningful_range']['address'])->toBe('A1:F4')->and($sheet['non_empty_cell_count'])->toBe(5);
});

it('keeps samples and histograms bounded for a larger dataset', function () {
    $rows = [1 => [1 => 'Reference', 2 => 'Amount']];
    for ($row = 2; $row <= 3001; $row++) {
        $rows[$row] = [1 => 'A'.$row, 2 => $row];
    }
    $table = waldSheet($rows)['regions'][0];
    expect($table['column_profiles'][0]['observed'])->toBe(3000)
        ->and($table['column_profiles'][0]['distinct_count_is_lower_bound'])->toBeTrue()
        ->and($table['column_profiles'][0]['uniqueness'])->toBeNull()
        ->and($table['column_profiles'][0]['samples'])->toHaveCount(5)
        ->and($table['column_profiles'][0]['common_values'])->toHaveCount(5)
        ->and($table['row_profiles'])->toHaveCount(200)
        ->and($table['row_profile_coverage']['sampled'])->toBeTrue();
});

it('rejects malformed CSV and unsupported encodings without silent data loss', function (string $contents) {
    expect(fn () => waldProfiler()->profile(Fixtures::file($contents), 'csv'))->toThrow(AnalysisProblem::class);
})->with(["Ref,Value\nA01,\"unterminated\n", "Ref,Value\nA01,\xFF\n", "Ref,Value\nA01,\0\n"]);

it('enforces CSV logical record limits including blank records', function () {
    expect(fn () => waldProfiler()->profile(Fixtures::file("A,B\n\n1,2\n"), 'csv', new AnalysisBudget(['rows' => 2])))->toThrow(AnalysisProblem::class);
});

it('rejects duplicate and invalid physical cell references', function (string $xml) {
    $path = Fixtures::xlsx([['rows' => []]], ['xl/worksheets/sheet1.xml' => $xml]);
    expect(fn () => waldProfiler()->profile($path, 'xlsx'))->toThrow(AnalysisProblem::class);
})->with([
    '<worksheet><sheetData><row r="1"><c r="A1"><v>1</v></c><c r="A1"><v>2</v></c></row></sheetData></worksheet>',
    '<worksheet><sheetData><row r="1"><c r="A2"><v>1</v></c></row></sheetData></worksheet>',
    '<worksheet><sheetData><row r="0"><c r="A0"><v>1</v></c></row></sheetData></worksheet>',
    '<not-a-worksheet/>',
]);

it('enforces merge and XML subtree safety budgets', function (string $limit) {
    $path = Fixtures::xlsx([['rows' => Fixtures::table(), 'merges' => ['A1:B1']]]);
    expect(fn () => waldProfiler()->profile($path, 'xlsx', new AnalysisBudget([$limit => 0])))->toThrow(AnalysisProblem::class);
})->with(['merges', 'node_bytes', 'styles']);

it('rejects non-local source paths before attempting access', function (string $path) {
    expect(fn () => (new WorkbookSourceFactory)->open($path, 'xlsx', new AnalysisBudget))->toThrow(AnalysisProblem::class);
})->with(['https://invalid.test/workbook.xlsx', '\\\\invalid.test\\private\\workbook.xlsx', '//invalid.test/private/workbook.xlsx']);

it('keeps its memory guard below the actual PHP limit and checks planned allocations', function () {
    $original = ini_get('memory_limit');
    try {
        ini_set('memory_limit', '128M');
        $budget = new AnalysisBudget;
        expect($budget->limit('memory_bytes'))->toBe(96 * 1024 * 1024);
        expect(fn () => $budget->reserve(128 * 1024 * 1024))->toThrow(AnalysisProblem::class);
    } finally {
        ini_set('memory_limit', $original);
    }
});
