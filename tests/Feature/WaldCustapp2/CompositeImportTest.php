<?php

use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\SourceImport\Integration\ImportReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;
use Tests\Support\WaldCustapp2Fixtures as C;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('projects plot and product facts from a blank call row without manufacturing a visit or service', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = C::reviewed($actor, $scope, [[
        'call' => '2001', 'plot' => ' 1 ', 'type' => null, 'complete' => 'Yes', 'products' => ['VS' => '2', 'BF' => '0'],
    ]]);

    expect($preview['blockers'])->toBe([]);
    $receipt = C::commit($actor, $scope, $preview);

    expect($receipt['counts'])->toBe(['applied' => 1, 'created' => 1, 'excluded' => 0, 'seen' => 1, 'unchanged' => 0, 'updated' => 0])
        ->and(ProjectedPlot::query()->where('plot_reference', '1')->count())->toBe(1)
        ->and(ProjectedPlotProduct::query()->count())->toBe(2)
        ->and(ProjectedPlotService::query()->count())->toBe(0)
        ->and(DB::table('wald_source_rows')->count())->toBe(1)
        ->and(DB::table('wald_source_row_observations')->count())->toBe(1)
        ->and(DB::table('wald_source_visits')->count())->toBe(0)
        ->and(DB::table('wald_visit_observations')->count())->toBe(0)
        ->and(DB::table('call_off_requests')->count())->toBe(0);
});

it('establishes a visit after a blank row without duplicating plot or source-row identity', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    C::commit($actor, $scope, C::reviewed($actor, $scope, [[
        'call' => '2001', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => '2'],
    ]]));
    C::commit($actor, $scope, C::reviewed($actor, $scope, [[
        'call' => '2001', 'plot' => '1', 'type' => 'PC1', 'complete' => 'No', 'products' => ['VS' => '2'],
    ]], slot: 'AFTERNOON'));

    expect(ProjectedPlot::query()->count())->toBe(1)
        ->and(DB::table('wald_source_rows')->count())->toBe(1)
        ->and(DB::table('wald_source_row_observations')->count())->toBe(2)
        ->and(DB::table('wald_source_visits')->where('call_type', 'PC1')->count())->toBe(1)
        ->and(DB::table('wald_visit_observations')->count())->toBe(1)
        ->and(ProjectedPlotService::query()->where('source_call_number', '2001')->count())->toBe(1);
});

it('keeps a different CallNo as a distinct source row while consolidating one plot', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = C::reviewed($actor, $scope, [
        ['call' => '2001', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => null]],
        ['call' => '2002', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => '0']],
    ]);
    C::commit($actor, $scope, $preview);

    expect(ProjectedPlot::query()->count())->toBe(1)
        ->and(DB::table('wald_source_rows')->count())->toBe(2)
        ->and(DB::table('wald_source_visits')->count())->toBe(0)
        ->and(ProjectedPlotProduct::query()->where('product_code', 'VS')->where('quantity', 0)->count())->toBe(1);
});

it('preserves an established visit when a newer partial export has a blank call type', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    C::commit($actor, $scope, C::reviewed($actor, $scope, [[
        'call' => '2001', 'plot' => '1', 'type' => 'PC1', 'complete' => 'Yes', 'products' => ['VS' => '2'],
    ]]));
    C::commit($actor, $scope, C::reviewed($actor, $scope, [[
        'call' => '2001', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => null],
    ]], slot: 'AFTERNOON'));

    expect(DB::table('wald_source_rows')->count())->toBe(1)
        ->and(DB::table('wald_source_row_observations')->count())->toBe(2)
        ->and(DB::table('wald_source_visits')->count())->toBe(1)
        ->and(DB::table('wald_visit_observations')->count())->toBe(1)
        ->and(ProjectedPlotService::query()->firstOrFail()->isSourceCompleted())->toBeTrue();
});

it('blocks a CallNo call-type identity change and contradictory plot product assertions', function (string $case) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);

    if ($case === 'visit identity') {
        C::commit($actor, $scope, C::reviewed($actor, $scope, [[
            'call' => '2001', 'plot' => '1', 'type' => 'PC1', 'complete' => 'No', 'products' => ['VS' => '2'],
        ]]));
        $preview = C::reviewed($actor, $scope, [[
            'call' => '2001', 'plot' => '1', 'type' => 'CC1', 'complete' => 'No', 'products' => ['VS' => '2'],
        ]], slot: 'AFTERNOON');
        expect($preview['blockers'])->toContain('source_row_visit_identity_conflict');
    } else {
        $state = C::staged($actor, $scope, [
            ['call' => '2001', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => '2']],
            ['call' => '2002', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => '3']],
        ]);
        $preview = (new ImportReview)->preview($actor, $scope, $state['run'], $state['epoch'], F::command());
        expect($preview['blockers'])->toContain('BLOCKED_STAGED_RECORDS');
    }
})->with(['visit identity', 'product conflict']);

it('keeps exact per-value physical provenance and private unmapped evidence', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $state = C::staged($actor, $scope, [[
        'call' => '2001', 'plot' => '1', 'type' => null, 'complete' => null, 'products' => ['VS' => '2'],
    ]]);
    $row = (new ImportReview)->details($actor, $scope, $state['run'])[0];

    expect($row['provenance']['logical_table'])->toBe('C2:E2+I2:AJ2')
        ->and($row['provenance']['values']['call_reference'])->toMatchArray([
            'sheet' => 'sheet-1', 'cell' => 'C4', 'logical_row' => 1, 'fragment' => 1,
        ])
        ->and($row['provenance']['values']['quantity:VS'])->toMatchArray([
            'sheet' => 'sheet-1', 'cell' => 'T4', 'logical_row' => 1, 'fragment' => 2,
        ])
        ->and($row['provenance']['unmapped_private_evidence'])->toBeArray();
});

it('keeps an unknown nonblank call type blocked and does not create Portal records', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $state = C::staged($actor, $scope, [[
        'call' => '2001', 'plot' => '1', 'type' => 'CM2', 'complete' => 'No', 'products' => ['VS' => '2'],
    ]]);
    $preview = (new ImportReview)->preview($actor, $scope, $state['run'], $state['epoch'], F::command());

    expect($preview['blockers'])->toContain('BLOCKED_STAGED_RECORDS')
        ->and(ProjectedPlot::query()->count())->toBe(0)
        ->and(DB::table('wald_source_rows')->count())->toBe(0)
        ->and(DB::table('wald_source_visits')->count())->toBe(0);
});
