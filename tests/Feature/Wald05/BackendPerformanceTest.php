<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use App\SourceImport\Integration\ImportConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('keeps read queries bounded through small medium and maximum explicit atomic units', function (int $count, bool $complete) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    if ($complete) {
        B::commit($actor, $scope, B::reviewed($actor, $scope, count: $count));
        $batch = CallOffBatch::factory()->create(['site_id' => $scope->siteId, 'submitted_by_user_id' => $actor->id]);
        foreach (ProjectedPlotService::query()->whereNotNull('source_call_number')->get() as $service) {
            CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id,
                'projected_plot_service_id' => $service->id, 'service_identifier' => CallOffServiceType::Windows,
                'status' => CallOffRequestStatus::DateAgreed, 'agreed_date' => '2026-10-12']);
        }
    }
    $start = microtime(true);
    $preview = B::reviewed($actor, $scope, $complete ? array_fill(0, $count, ['complete' => 'Yes']) : [], slot: $complete ? 'AFTERNOON' : 'MORNING', count: $count);
    $selects = 0;
    $writes = 0;
    $enabled = true;
    DB::listen(function ($query) use (&$selects, &$writes, &$enabled) {
        if ($enabled) {
            str_starts_with(strtolower($query->sql), 'select') ? $selects++ : $writes++;
        }
    });
    B::commit($actor, $scope, $preview);
    $enabled = false;
    expect(DB::table('wald_source_visits')->count())->toBe($count)->and($selects)->toBeLessThan(90)
        ->and(memory_get_usage(true))->toBeLessThan(384 * 1024 * 1024);
    if ($complete) {
        expect(CallOffRequest::query()->where('status', CallOffRequestStatus::Completed)->whereDate('agreed_date', '2026-10-12')->count())->toBe($count);
    }
    fwrite(STDERR, json_encode(['wald05_benchmark' => $count, 'source_complete' => $complete, 'seconds' => round(microtime(true) - $start, 3), 'commit_selects' => $selects, 'commit_writes' => $writes, 'memory_bytes' => memory_get_usage(true)]).PHP_EOL);
})->with([[7, false], [100, false], [500, false], [7, true], [100, true], [500, true]]);

it('refuses oversized units rather than committing a hidden subset', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    expect(fn () => B::staged($actor, $scope, count: 501))->toThrow(ImportConflict::class, 'explicit_run_row_limit_exceeded');
    expect(DB::table('wald_import_receipts')->count())->toBe(0);
});
