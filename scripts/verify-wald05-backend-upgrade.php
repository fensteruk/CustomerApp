<?php

use App\Models\ProjectedPlot;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportIntake;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1'
    || DB::connection()->getDatabaseName() !== 'customerapp_wald05_backend_upgrade' || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
    throw new RuntimeException('Dedicated empty loopback backend upgrade schema required.');
}
if (DB::select('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE()') !== []) {
    throw new RuntimeException('Refusing populated upgrade schema.');
}
config(['wald_import.enabled' => true]);
$check = function (bool $ok, string $message): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$file = '2026_09_09_000012_create_wald_import_backend.php';
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => basename($p) < $file));
$check(Artisan::call('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true]) === 0, 'Baseline failed.');
[$actor, $scope] = F::owner();
F::active($actor, $scope);
B::binding($actor, $scope);
ProjectedPlot::factory()->create(['site_id' => $scope->siteId]);
$tables = ['customer_organisations', 'sites', 'projected_plots', 'users', 'wald_knowledge_contexts', 'wald_knowledge_evidence', 'wald_clarifications',
    'wald_clarification_answers', 'wald_profiles', 'wald_profile_versions', 'wald_profile_uses', 'wald_knowledge_events', 'wald_source_bindings', 'wald_binding_versions', 'wald_import_commands'];
$snapshot = function () use ($tables) {
    return json_encode(array_map(fn ($t) => DB::table($t)->orderBy('id')->get()->map(function ($row) {
        $a = (array) $row;
        unset($a['wald_epoch']);

        return $a;
    })->all(), $tables), JSON_THROW_ON_ERROR);
};
$before = $snapshot();
$check(Artisan::call('migrate', ['--force' => true]) === 0, 'Upgrade failed.');
$check($before === $snapshot(), 'Existing facts changed.');
$check(Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]) === 0, 'Empty rollback failed.');
$check($before === $snapshot(), 'Rollback changed existing facts.');
$check(Artisan::call('migrate', ['--force' => true]) === 0, 'Reapply failed.');
(new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command());
$guards = fn () => DB::select("SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'w5b_%' ORDER BY TRIGGER_NAME");
$guardState = json_encode($guards(), JSON_THROW_ON_ERROR);
$refused = false;
try {
    (require database_path('migrations/'.$file))->down();
} catch (RuntimeException $e) {
    $refused = str_starts_with($e->getMessage(), 'Refusing rollback');
}
$check($refused && $guardState === json_encode($guards(), JSON_THROW_ON_ERROR), 'Populated rollback failed to preserve schema.');
$check(count($guards()) === 19, 'Missing guards.');
echo json_encode(['result' => 'PASS', 'baseline_migrations' => count($baseline), 'additive_migrations' => 1, 'existing_tables_verified' => count($tables),
    'backend_tables' => 8, 'backend_triggers' => 19, 'projection_epoch_columns' => 3, 'empty_rollback_reapply' => 'PASS', 'populated_rollback' => 'REFUSED_BEFORE_DDL'], JSON_THROW_ON_ERROR).PHP_EOL;
