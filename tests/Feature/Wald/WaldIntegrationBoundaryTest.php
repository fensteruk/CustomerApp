<?php

use App\Wald\Services\WorkbookProfiler;
use Illuminate\Support\Facades\DB;
use Tests\Support\WaldFixtures;

it('resolves the structural service through Laravel without database reads writes or staging side effects', function () {
    $path = WaldFixtures::xlsx([['rows' => WaldFixtures::table()]]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $profile = app(WorkbookProfiler::class)->profile($path, 'xlsx')->toArray();
        expect(DB::getQueryLog())->toBe([])
            ->and($profile['scope'])->toBe('structural_analysis_only')
            ->and($profile['ready_for_staging'])->toBeFalse()
            ->and($profile['non_empty_cell_count'])->toBe(9);
    } finally {
        DB::disableQueryLog();
        WaldFixtures::cleanup();
    }
});
