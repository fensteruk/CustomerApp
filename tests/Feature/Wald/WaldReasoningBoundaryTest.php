<?php

use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\WorkbookProfiler;
use Illuminate\Support\Facades\DB;
use Tests\Support\WaldFixtures;

it('resolves reasoning through Laravel without database reads writes or staging mutations', function () {
    $path = WaldFixtures::xlsx([['rows' => WaldFixtures::table()]]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $profile = app(WorkbookProfiler::class)->profile($path, 'xlsx');
        $result = app(ReasoningEngine::class)->reason($profile)->toArray();
        expect(DB::getQueryLog())->toBe([])
            ->and($result['scope'])->toBe('generic_shape_reasoning_only')
            ->and($result['ready_for_staging'])->toBeFalse()
            ->and($result['hypotheses'])->not->toBeEmpty()
            ->and($result['manifest']['source_checksum'])->toBe($profile->toArray()['source_checksum']);
    } finally {
        DB::disableQueryLog();
        WaldFixtures::cleanup();
    }
});
