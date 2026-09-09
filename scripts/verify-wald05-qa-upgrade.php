<?php

use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || DB::connection()->getDatabaseName() !== 'customerapp_wald05_qa_upgrade_v2'
    || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')
    || DB::select('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE()') !== []) {
    throw new RuntimeException('Exact empty disposable WALD05 QA MySQL 8.4 schema required.');
}
config(['wald_import.enabled' => true]);
$check = function ($ok, $message) {
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => basename($p) < '2026_09_08_000011'));
$check(count($baseline) === 13, 'Expected accepted WALD04 migration baseline.');
$check(Artisan::call('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true]) === 0, 'Baseline failed.');
[$actor, $scope] = F::owner();
F::active($actor, $scope);
$plot = ProjectedPlot::factory()->create(['site_id' => $scope->siteId]);
$service = ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => 'windows']);
ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'BF', 'quantity' => '2.125']);
$batch = CallOffBatch::factory()->create(['site_id' => $scope->siteId, 'submitted_by_user_id' => $actor->id]);
$request = CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id, 'projected_plot_service_id' => $service->id,
    'status' => 'date_agreed', 'agreed_date' => '2026-10-12']);
CallOffStatusHistory::factory()->create(['call_off_batch_id' => $batch->id, 'call_off_request_id' => $request->id, 'performed_by_user_id' => $actor->id]);
$tables = array_map(fn ($r) => $r->TABLE_NAME, DB::select("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name <> 'migrations' ORDER BY TABLE_NAME"));
$snapshot = function () use ($tables) {
    $data = [];
    foreach ($tables as $table) {
        $rows = DB::table($table)->get()->map(function ($row) {
            $a = (array) $row;
            unset($a['wald_epoch']);
            ksort($a);

            return json_encode($a, JSON_THROW_ON_ERROR);
        })->all();
        sort($rows);
        $data[$table] = $rows;
    }

    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
};
$before = $snapshot();
$check(Artisan::call('migrate', ['--force' => true]) === 0 && $before === $snapshot(), 'Additive upgrade changed existing facts.');
$check(Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]) === 0 && $before === $snapshot(), 'Empty combined rollback failed.');
$check(Artisan::call('migrate', ['--force' => true]) === 0 && $before === $snapshot(), 'Combined reapply failed.');
B::binding($actor, $scope);
$preview = B::reviewed($actor, $scope);
B::commit($actor, $scope, $preview);
$guards = fn () => json_encode(DB::select('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() ORDER BY TRIGGER_NAME'), JSON_THROW_ON_ERROR);
$guardHash = $guards();
foreach (['2026_09_08_000011_create_wald_import_foundation.php', '2026_09_09_000012_create_wald_import_backend.php'] as $migration) {
    $refused = false;
    try {
        (require database_path('migrations/'.$migration))->down();
    } catch (RuntimeException $e) {
        $refused = str_starts_with($e->getMessage(), 'Refusing rollback');
    }
    $check($refused && $guardHash === $guards(), 'Populated rollback did not preserve all guards.');
}
echo json_encode(['result' => 'PASS', 'accepted_wald04_migrations' => 13, 'additive_migrations' => 2, 'existing_tables_snapshotted' => count($tables),
    'preserved' => 'all pre-existing rows including Portal dates/products/history and knowledge', 'empty_rollback_reapply' => 'PASS', 'populated_rollback' => 'BOTH_REFUSED_BEFORE_DDL'], JSON_THROW_ON_ERROR).PHP_EOL;
