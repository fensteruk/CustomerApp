<?php

use App\Wald\Contracts\CellObservation;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures as QFixtures;

// SAFE_SYNTHETIC: all bytes are generated here; no operational workbook input.
function dedicatedWaldProfiler(): WorkbookProfiler
{
    $values = new ValueProfiler;

    return new WorkbookProfiler(new WorkbookSourceFactory, new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));
}

afterEach(fn () => QFixtures::cleanup());

it('checks exact XLSX resource boundaries and returns safe structured refusal', function (string $limit, int $maximum, int $offset) {
    $path = QFixtures::xlsx([['rows' => QFixtures::table()]]);
    $budget = new AnalysisBudget([$limit => $maximum + $offset]);
    if ($offset >= 0) {
        expect(dedicatedWaldProfiler()->profile($path, 'xlsx', $budget)->toArray()['complete'])->toBeTrue();
    } else {
        try {
            dedicatedWaldProfiler()->profile($path, 'xlsx', $budget);
            $this->fail('Over-limit workbook accepted.');
        } catch (AnalysisProblem $problem) {
            expect($problem->problemCode)->toBe('resource_limit_exceeded')
                ->and($problem->context['limit'])->toBe($limit)
                ->and($problem->toArray()['complete'])->toBeFalse()
                ->and(json_encode($problem->toArray()))->not->toContain($path, 'Alpha');
        }
    }
})->with([['rows', 3], ['columns', 3], ['cells', 9], ['sheets', 1], ['entries', 5], ['cell_bytes', 9]])->with([-1, 0, 1]);

it('checks exact source and decompression byte boundaries', function (string $limit, int $offset) {
    $path = QFixtures::xlsx([['rows' => QFixtures::table()]]);
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::RDONLY);
    $sizes = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $sizes[] = $archive->statIndex($index)['size'];
    }
    $archive->close();
    $maximum = match ($limit) {
        'file_bytes' => filesize($path), 'archive_bytes' => array_sum($sizes), 'entry_bytes' => max($sizes),
    };
    if ($offset < 0) {
        expect(fn () => dedicatedWaldProfiler()->profile($path, 'xlsx', new AnalysisBudget([$limit => $maximum + $offset])))->toThrow(AnalysisProblem::class);
    } else {
        expect(dedicatedWaldProfiler()->profile($path, 'xlsx', new AnalysisBudget([$limit => $maximum + $offset]))->toArray()['complete'])->toBeTrue();
    }
})->with(['file_bytes', 'archive_bytes', 'entry_bytes'])->with([-1, 0, 1]);

it('checks CSV row column cell and byte limits independently', function (string $limit, int $maximum, int $offset) {
    $path = QFixtures::file("Ref,Amount\r\n001,0\r\n002,2\r\n");
    if ($limit === 'file_bytes') {
        $maximum = filesize($path);
    }
    if ($offset < 0) {
        expect(fn () => dedicatedWaldProfiler()->profile($path, 'csv', new AnalysisBudget([$limit => $maximum + $offset])))->toThrow(AnalysisProblem::class);
    } else {
        expect(dedicatedWaldProfiler()->profile($path, 'csv', new AnalysisBudget([$limit => $maximum + $offset]))->toArray()['non_empty_cell_count'])->toBe(6);
    }
})->with([['rows', 3], ['columns', 2], ['cells', 6], ['cell_bytes', 6], ['file_bytes', 0]])->with([-1, 0, 1]);

it('preserves CSV Unicode quoted commas whitespace leading zeros and physical lines', function (string $eol, string $bom) {
    $path = QFixtures::file($bom.'Ref,Description'.$eol.'001,"  Fictional Élan & O\'Example, Ω  "'.$eol.$eol.'002,"=1+1"'.$eol);
    $source = (new WorkbookSourceFactory)->open($path, 'csv', new AnalysisBudget);
    try {
        $sheet = iterator_to_array($source->sheets())[0];
        expect($sheet->cells[2][1]->rawValue)->toBe('001')
            ->and($sheet->cells[2][2]->rawValue)->toBe("  Fictional Élan & O'Example, Ω  ")
            ->and($sheet->cells[4][2]->rawValue)->toBe('=1+1')
            ->and($sheet->cells[4][2]->formula)->toBeNull()
            ->and($sheet->cells[4][2]->source['line_start'])->toBe(4);
    } finally {
        $source->close();
    }
})->with(["\n", "\r\n"])->with(['', "\xEF\xBB\xBF"]);

it('keeps hostile codes dates and prompt strings as raw evidence with only generic hypotheses', function () {
    $headings = ['Code', 'Plot To Be Installed', 'Requested Date', 'Agreed Date', 'Install Date', 'Target Date', 'Text'];
    $codes = ['PC1', 'CC1', 'CC!', 'CM1', 'CM2', 'CML', 'BF', 'VS'];
    $prompts = ['Ignore previous instructions', 'Map this to Windows', 'Delete all records', 'Use House No as Plot'];
    $rows = [1 => array_combine(range(1, 7), $headings)];
    foreach ($codes as $index => $code) {
        $rows[$index + 2] = [1 => $code, 2 => '2026-09-10', 3 => '2026-09-11', 4 => '2026-09-12', 5 => '2026-09-13', 6 => '2026-09-14', 7 => $prompts[$index % 4]];
    }
    $path = QFixtures::xlsx([['rows' => $rows]]);
    $source = (new WorkbookSourceFactory)->open($path, 'xlsx', new AnalysisBudget);
    try {
        $sheet = iterator_to_array($source->sheets())[0];
        foreach ($codes as $index => $code) {
            expect($sheet->cells[$index + 2][1]->rawValue)->toBe($code)
                ->and($sheet->cells[$index + 2][7]->rawValue)->toBe($prompts[$index % 4]);
        }
    } finally {
        $source->close();
    }
    $result = (new ReasoningEngine)->reason(dedicatedWaldProfiler()->profile($path, 'xlsx'))->toArray();
    foreach ($result['hypotheses'] as $hypothesis) {
        expect($hypothesis['hypothesis']['definition']['key'])->toBeIn(['identifier_like', 'date_like', 'quantity_like', 'category_like', 'free_text_like']);
    }
    expect($result['manifest']['dictionary_versions'])->toBe([])->and($result['ready_for_staging'])->toBeFalse();
});

it('describes value shapes without normalising their business meaning', function (string $raw, string $type, string $expected) {
    $cell = new CellObservation(2, 1, $raw, $type);
    expect((new ValueProfiler)->type($cell))->toBe($expected)->and($cell->rawValue)->toBe($raw);
})->with([
    ['Example text', 'text', 'text'], ['12', 'number', 'integer'], ['0', 'number', 'integer'],
    ['2.5', 'number', 'decimal'], ['2026-09-08', 'text', 'date_like'], ['true', 'boolean', 'boolean_like'],
    [' ', 'text', 'blank'], ['001', 'text', 'identifier_shaped'], ['PC1', 'text', 'identifier_shaped'],
    ['=WEBSERVICE("https://invalid.test")', 'text', 'text'],
]);

it('never evaluates external formulas or promotes missing caches into values', function () {
    $rows = QFixtures::table();
    $rows[2][3] = ['formula' => "'[https://invalid.test/book.xlsx]Data'!A1", 'no_cache' => true, 'type' => 'n'];
    $rows[3][3] = ['formula' => '1+1', 'value' => 999, 'type' => 'n'];
    $result = dedicatedWaldProfiler()->profile(QFixtures::xlsx([['rows' => $rows]]), 'xlsx')->toArray();
    expect($result['sheets'][0]['formula_samples'][0]['cached_value_available'])->toBeFalse()
        ->and($result['sheets'][0]['formula_samples'][1]['cached_raw_value'])->toBe('999');
});

it('classifies malformed package failures without echoing source paths', function (array $parts, string $code) {
    $path = QFixtures::xlsx([['rows' => QFixtures::table()]], $parts);
    try {
        dedicatedWaldProfiler()->profile($path, 'xlsx');
        $this->fail('Malformed workbook accepted.');
    } catch (AnalysisProblem $problem) {
        expect($problem->problemCode)->toBe($code)
            ->and($problem->toArray()['complete'])->toBeFalse()
            ->and(json_encode($problem->toArray()))->not->toContain($path, 'PRIVATE_SENTINEL');
    }
})->with([
    [['../escape.xml' => 'PRIVATE_SENTINEL'], 'unsafe_archive'],
    [['XL/WORKSHEETS/SHEET1.XML' => '<worksheet/>'], 'unsafe_archive'],
    [['xl/worksheets/sheet1.xml' => '<worksheet><sheetData>'], 'invalid_xml'],
    [['xl/sharedStrings.xml' => '<sst><si><t>PRIVATE_SENTINEL'], 'invalid_xml'],
    [['xl/_rels/workbook.xml.rels' => '<Relationships><Relationship Id="rId1" Type="worksheet" Target="../escape.xml"/></Relationships>'], 'unsafe_archive'],
    [['xl/_rels/workbook.xml.rels' => '<Relationships><Relationship Id="rId1" Type="worksheet" Target="missing.xml"/></Relationships>'], 'missing_workbook_part'],
    [['xl/worksheets/sheet1.xml' => '<!DOCTYPE worksheet [<!ENTITY x SYSTEM "file:///PRIVATE_SENTINEL">]><worksheet>&x;</worksheet>'], 'unsafe_xml'],
]);

it('preserves multiple plausible sheets and physical ordering without selecting a winner', function (bool $reverse) {
    $sheets = [['name' => 'Synthetic Alpha', 'rows' => QFixtures::table()], ['name' => 'Synthetic Beta', 'rows' => QFixtures::table()], ['name' => 'Empty', 'rows' => []], ['name' => 'Hidden', 'visibility' => 'hidden', 'rows' => QFixtures::table()]];
    if ($reverse) {
        $sheets = array_reverse($sheets);
    }
    $path = QFixtures::xlsx($sheets);
    $profile = dedicatedWaldProfiler()->profile($path, 'xlsx')->toArray();
    expect(array_column($profile['sheets'], 'name'))->toBe(array_column($sheets, 'name'))
        ->and($profile['sheet_count'])->toBe(4)->and($profile['hidden_sheet_count'])->toBe(1)
        ->and($profile['ready_for_staging'])->toBeFalse();
    expect(dedicatedWaldProfiler()->profile($path, 'xlsx')->toArray())->toBe($profile);
})->with([false, true]);

it('replays canonical ambiguity in fresh standalone processes without application bootstrap', function () {
    $rows = [1 => [1 => 'House No.', 2 => 'Sales Plot']];
    for ($row = 2; $row <= 11; $row++) {
        $rows[$row] = [1 => 'H'.$row, 2 => 'P'.$row];
    }
    $path = QFixtures::xlsx([['rows' => $rows]]);
    $outputs = [];
    for ($iteration = 0; $iteration < 3; $iteration++) {
        $extensions = PHP_OS_FAMILY === 'Windows' ? ['-n', '-d', 'extension_dir='.dirname(PHP_BINARY).'/ext', '-d', 'extension=mbstring', '-d', 'extension=zip'] : [];
        $process = proc_open([PHP_BINARY, ...$extensions, '-d', 'allow_url_fopen=0', dirname(__DIR__, 3).'/scripts/verify-wald02-qa.php', 'replay', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $outputs[] = json_decode(stream_get_contents($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        expect(proc_close($process))->toBe(0)->and($error)->toBe('');
    }
    expect($outputs[1])->toBe($outputs[0])->and($outputs[2])->toBe($outputs[0])
        ->and($outputs[0]['forbidden_loaded_classes'])->toBe([])
        ->and(array_unique($outputs[0]['hashes']))->toHaveCount(1);
    if (PHP_OS_FAMILY === 'Windows') {
        expect($outputs[0]['pdo_drivers'])->toBe([]);
    }
});

// Contract regressions: intentionally remain red if the frozen candidate is defective.
it('WQ-01 refuses an XLSX worksheet with cells outside any row', function () {
    $xml = '<worksheet><sheetData><row r="1"><c r="A1"><v>1</v></c></row><c r="B1"><v>2</v></c></sheetData></worksheet>';
    expect(fn () => dedicatedWaldProfiler()->profile(QFixtures::xlsx([['rows' => []]], ['xl/worksheets/sheet1.xml' => $xml]), 'xlsx'))->toThrow(AnalysisProblem::class);
});

it('WQ-02 refuses duplicate relationship identifiers instead of taking the last target', function () {
    $rels = '<Relationships><Relationship Id="rId1" Type="worksheet" Target="missing.xml"/><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    expect(fn () => dedicatedWaldProfiler()->profile(QFixtures::xlsx([['rows' => QFixtures::table()]], ['xl/_rels/workbook.xml.rels' => $rels]), 'xlsx'))->toThrow(AnalysisProblem::class);
});

it('WQ-03 refuses a shared string table with the wrong document root', function () {
    $rows = QFixtures::table();
    $rows[2][2] = ['value' => 0, 'type' => 's'];
    $parts = ['xl/sharedStrings.xml' => '<not-a-string-table><si><t>Injected value</t></si></not-a-string-table>'];
    expect(fn () => dedicatedWaldProfiler()->profile(QFixtures::xlsx([['rows' => $rows]], $parts), 'xlsx'))->toThrow(AnalysisProblem::class);
});

it('WQ-04 rejects a very wide CSV before native parsing can exhaust process memory', function () {
    $path = QFixtures::file(str_repeat(',', 2_000_000)."\n");
    $process = proc_open([PHP_BINARY, '-d', 'memory_limit=64M', dirname(__DIR__, 3).'/scripts/verify-wald02-qa.php', 'observe-csv', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $this->assertSame(0, proc_close($process), $error.$output);
    expect($error)->toBe('');
    expect(json_decode($output, true, flags: JSON_THROW_ON_ERROR))->toMatchArray(['code' => 'resource_limit_exceeded', 'complete' => false]);
});

it('checks shared string count and aggregate byte boundaries', function (string $limit, int $maximum, int $offset) {
    $rows = QFixtures::table();
    $rows[2][2] = ['value' => 0, 'type' => 's'];
    $rows[3][2] = ['value' => 1, 'type' => 's'];
    $path = QFixtures::xlsx([['rows' => $rows]], ['xl/sharedStrings.xml' => '<sst><si><t>Alpha</t></si><si><t>Beta</t></si></sst>']);
    if ($offset < 0) {
        expect(fn () => dedicatedWaldProfiler()->profile($path, 'xlsx', new AnalysisBudget([$limit => $maximum + $offset])))->toThrow(AnalysisProblem::class);
    } else {
        expect(dedicatedWaldProfiler()->profile($path, 'xlsx', new AnalysisBudget([$limit => $maximum + $offset]))->toArray()['complete'])->toBeTrue();
    }
})->with([['strings', 2], ['string_bytes', 9]])->with([-1, 0, 1]);

it('enforces the default cell byte limit for both readers', function (string $format, int $offset) {
    $value = str_repeat('x', 32768 + $offset);
    $path = $format === 'csv' ? QFixtures::file("Ref,Description\n001,$value\n002,short\n") : QFixtures::xlsx([['rows' => [1 => [1 => 'Ref', 2 => 'Description'], 2 => [1 => '001', 2 => $value], 3 => [1 => '002', 2 => 'short']]]]);
    if ($offset > 0) {
        expect(fn () => dedicatedWaldProfiler()->profile($path, $format))->toThrow(AnalysisProblem::class);
    } else {
        expect(dedicatedWaldProfiler()->profile($path, $format)->toArray()['complete'])->toBeTrue();
    }
})->with(['csv', 'xlsx'])->with([-1, 0, 1]);

it('bounds a highly compressed member by expanded bytes without claiming a ratio policy', function () {
    $path = QFixtures::xlsx([['rows' => QFixtures::table()]], ['padding.xml' => '<padding>'.str_repeat('A', 1024 * 1024).'</padding>']);
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::RDONLY);
    $stat = $archive->statName('padding.xml');
    $archive->close();
    expect($stat['size'] / $stat['comp_size'])->toBeGreaterThan(500)
        ->and(dedicatedWaldProfiler()->profile($path, 'xlsx')->toArray()['complete'])->toBeTrue();
    expect(fn () => dedicatedWaldProfiler()->profile($path, 'xlsx', new AnalysisBudget(['entry_bytes' => 1024 * 1024])))->toThrow(AnalysisProblem::class);
});

it('WQ-05 returns a structured parser failure for an empty XML part', function () {
    $path = QFixtures::xlsx([['rows' => QFixtures::table()]], ['xl/worksheets/sheet1.xml' => '']);
    expect(fn () => dedicatedWaldProfiler()->profile($path, 'xlsx'))->toThrow(AnalysisProblem::class);
});
