<?php

use App\Models\ProjectedPlot;
use App\SourceImport\Integration\SourceBindingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || DB::connection()->getDatabaseName() !== 'customerapp_wald05_upgrade'
    || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
    throw new RuntimeException('Dedicated empty WALD05 upgrade schema on loopback MySQL 8.4 required.');
}
if (DB::select('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE()') !== []) {
    throw new RuntimeException('Refusing to reuse a populated upgrade schema.');
}
config(['wald_import.enabled' => true]);
$check = function (bool $ok, string $message): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$migrationFile = '2026_09_08_000011_create_wald_import_foundation.php';
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => basename($p) < $migrationFile));
$check(Artisan::call('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true]) === 0, 'Baseline migration failed.');
try {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    ProjectedPlot::factory()->create(['site_id' => $scope->siteId]);
    $tables = ['customer_organisations', 'sites', 'projected_plots', 'users', 'wald_knowledge_contexts', 'wald_knowledge_evidence',
        'wald_clarifications', 'wald_clarification_answers', 'wald_profiles', 'wald_profile_versions', 'wald_profile_uses', 'wald_knowledge_events'];
    $snapshot = fn () => json_encode(array_map(fn ($t) => DB::table($t)->orderBy('id')->get()->all(), $tables), JSON_THROW_ON_ERROR);
    $before = $snapshot();
    $check(Artisan::call('migrate', ['--force' => true]) === 0, 'Additive migration failed.');
    $check($before === $snapshot(), 'Existing Portal or knowledge rows changed.');
    $check(Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]) === 0, 'Empty foundation rollback failed.');
    $check($before === $snapshot(), 'Empty rollback changed existing rows.');
    $check(Artisan::call('migrate', ['--force' => true]) === 0, 'Foundation reapply failed.');
    $service = new SourceBindingService;
    $draft = $service->draft($office, $scope, 'SOURCE_SITE_ID', 'synthetic-permanent-id', 'Synthetic migration evidence.', F::command());
    $service->activate($office, $scope, $draft['binding'], 1, $draft['definition_hash'], $draft['epoch'], 'Synthetic activation.', F::command());
    $schema = fn () => DB::select("SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'w5_%' ORDER BY TRIGGER_NAME");
    $guards = json_encode($schema(), JSON_THROW_ON_ERROR);
    $refused = false;
    try {
        (require database_path('migrations/'.$migrationFile))->down();
    } catch (RuntimeException $e) {
        $refused = str_starts_with($e->getMessage(), 'Refusing rollback');
    }
    $check($refused && $guards === json_encode($schema(), JSON_THROW_ON_ERROR), 'Populated rollback did not preserve schema.');
    $check($before === $snapshot(), 'Foundation use changed existing rows.');
    $check(count($schema()) === 6, 'Missing immutability guards.');
    echo json_encode(['result' => 'PASS', 'mysql' => DB::selectOne('SELECT VERSION() AS v')->v,
        'baseline_migrations' => count($baseline), 'additive_migrations' => 1, 'existing_tables_verified' => count($tables),
        'foundation_tables' => 3, 'foundation_triggers' => 6, 'empty_rollback_reapply' => 'PASS',
        'populated_rollback' => 'REFUSED_BEFORE_DDL'], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    WaldFixtures::cleanup();
}
