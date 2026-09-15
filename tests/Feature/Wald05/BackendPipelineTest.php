<?php

use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('privately uploads stages reviews and atomically commits an exact synthetic workbook', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    expect(ProjectedPlot::query()->count())->toBe(0);
    $receipt = B::commit($actor, $scope, $preview);
    expect($receipt['counts'])->toBe(['applied' => 7, 'created' => 7, 'excluded' => 0, 'seen' => 7, 'unchanged' => 0, 'updated' => 0]);
    expect(ProjectedPlot::query()->count())->toBe(7)->and(ProjectedPlotProduct::query()->count())->toBe(14)
        ->and(DB::table('wald_import_receipts')->count())->toBe(1)->and(DB::table('wald_visit_observations')->count())->toBe(7)
        ->and((new ImportIntake)->status($actor, $scope, $preview['run'])['state'])->toBe('COMMITTED');
    expect(B::commit($actor, $scope, $preview))->toBe($receipt);
});

it('preserves absent and blank products while applying an explicit zero under newer ordering', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $next = B::reviewed($actor, $scope, [0 => ['VS' => ''], 1 => ['VS' => '0']], slot: 'AFTERNOON', headers: ['Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS']);
    B::commit($actor, $scope, $next);
    expect(ProjectedPlotProduct::query()->where('product_code', 'BF')->where('quantity', '1.000')->count())->toBe(7)
        ->and(ProjectedPlotProduct::query()->where('product_code', 'VS')->where('quantity', 0)->count())->toBe(1)
        ->and(ProjectedPlotProduct::query()->where('product_code', 'VS')->where('quantity', '2.125')->count())->toBe(6);
});

it('blocks duplicates and unknown source meanings without projection effects', function (array $change) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $run = B::staged($actor, $scope, [1 => $change]);
    $preview = (new ImportReview)->preview($actor, $scope, $run['run'], $run['epoch'], F::command());
    expect($preview['blockers'])->toContain('BLOCKED_STAGED_RECORDS');
    expect(fn () => (new ImportReview)->approve($actor, $scope, $run['run'], $preview['preview'], $preview['hash'], F::command()))->toThrow(ImportConflict::class);
    expect(ProjectedPlot::query()->count())->toBe(0);
})->with([[['Call No.' => '1001']], [['Call Type' => 'CC!']], [['Call Type' => 'ZZ9', 'complete' => 'Yes']], [['VS' => '-1']], [['complete' => 'perhaps']]]);

it('keeps excluded products commercial fields and operational dates out of Portal projections', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope, [0 => ['CAS' => '100', 'FLU' => '200', 'Site Value' => '999999', 'Items Ordered Status' => 'Complete', 'Plot To Be Installed' => '2026-10-12']],
        headers: ['Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF', 'CAS', 'FLU', 'Site Value', 'Items Ordered Status', 'Plot To Be Installed']);
    B::commit($actor, $scope, $preview);
    expect(ProjectedPlotProduct::query()->whereIn('product_code', ['CAS', 'FLU'])->exists())->toBeFalse()
        ->and(ProjectedPlotService::query()->whereNotNull('source_completion_observed_at')->exists())->toBeFalse()
        ->and(DB::table('call_off_requests')->count())->toBe(0);
});

it('does not turn typed date percentage boolean or formula cells into quantities after header review', function (array $cell) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $state = B::staged($actor, $scope, [0 => ['VS' => $cell]]);
    $preview = (new ImportReview)->preview($actor, $scope, $state['run'], $state['epoch'], F::command());
    expect($preview['blockers'])->toContain('BLOCKED_STAGED_RECORDS');
})->with([[['value' => 45000, 'style' => 2]], [['value' => 0.25, 'style' => 3]], [['value' => '1', 'type' => 'b']], [['value' => '3', 'formula' => '1+2', 'type' => 'n']]]);
