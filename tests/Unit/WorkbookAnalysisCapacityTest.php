<?php

use App\SourceImport\Integration\CapturedWorkbookSource;
use App\SourceImport\Integration\WorkbookAnalysisCapacity;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures;

it('captures one guarded pass with exactly the existing structural result', function (): void {
    $path = WaldFixtures::xlsx([['rows' => WaldFixtures::table()]]);
    $values = new ValueProfiler;
    $profiler = new WorkbookProfiler(new WorkbookSourceFactory, new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));
    $expected = $profiler->profile($path, 'xlsx')->toArray();
    $capture = new CapturedWorkbookSource((new WorkbookSourceFactory)->open($path, 'xlsx', WorkbookAnalysisCapacity::budget()));
    try {
        $actual = $profiler->analyse($capture, hash_file('sha256', $path))->toArray();
        expect($actual)->toBe($expected)->and($capture->observations())->toHaveCount(1);
        expect(fn () => iterator_to_array($capture->sheets()))->toThrow(LogicException::class, 'captured_workbook_already_consumed');
    } finally {
        $capture->close();
    }
});

it('keeps generic defaults and every non-row resource guard unchanged', function (): void {
    $old = new AnalysisBudget;
    $large = WorkbookAnalysisCapacity::budget();
    expect($old->limit('rows'))->toBe(10000)->and($large->limit('rows'))->toBe(20001);
    foreach (['cells', 'columns', 'memory_bytes', 'file_bytes', 'archive_bytes', 'entry_bytes', 'cell_bytes', 'strings', 'string_bytes', 'node_bytes'] as $key) {
        expect($large->limit($key))->toBe($old->limit($key));
    }
    $large->guard('rows', 20001);
    expect(fn () => $large->guard('rows', 20002))->toThrow(AnalysisProblem::class)
        ->and(fn () => WorkbookAnalysisCapacity::guardDuration(hrtime(true) - 41_000_000_000))->toThrow(AnalysisProblem::class);
});
