<?php

use App\Models\CustomerOrganisation;
use App\Models\Site;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || $database !== 'customerapp_wald04_qa_upgrade'
    || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
    throw new RuntimeException('Dedicated empty QA upgrade database on local MySQL 8.4 required.');
}
if (DB::select('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE()') !== []) {
    throw new RuntimeException('Refusing to reuse a populated schema.');
}
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$migrate = function (array $arguments = []) use ($check): void {
    $check(Artisan::call('migrate', $arguments + ['--force' => true]) === 0, 'Migration command failed.');
};
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')),
    fn ($path) => basename($path) < '2026_09_08_000009_create_wald_knowledge.php'));
$migrate(['--path' => $baseline, '--realpath' => true]);
$org = CustomerOrganisation::factory()->create(['name' => 'QA synthetic upgrade owner']);
$site = Site::factory()->create(['customer_organisation_id' => $org->id, 'name' => 'QA synthetic upgrade site']);
$before = [$org->fresh()->getRawOriginal(), $site->fresh()->getRawOriginal()];
$migrate();
$check([$org->fresh()->getRawOriginal(), $site->fresh()->getRawOriginal()] === $before, 'Existing source rows changed.');
$check((int) DB::table('migrations')->count() === count($baseline) + 2, 'Unexpected migration count.');
$verifySchema = function () use ($check): array {
    $tables = DB::select("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'wald_%' ORDER BY TABLE_NAME");
    $triggers = DB::select("SELECT TRIGGER_NAME, ACTION_STATEMENT FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'w4_%' ORDER BY TRIGGER_NAME");
    $check(count($tables) === 8 && count($triggers) === 16, 'Incomplete Wald schema.');

    return [$tables, $triggers];
};
$verifySchema();
// Empty Wald rollback/reapply is allowed even with existing non-Wald portal rows.
$check(Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]) === 0, 'Empty rollback failed.');
$check(DB::select("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'wald_%'") === [], 'Wald tables survived empty rollback.');
$migrate();
$verifySchema();
$check([$org->fresh()->getRawOriginal(), $site->fresh()->getRawOriginal()] === $before, 'Empty rollback changed source rows.');
try {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    $schemaBefore = json_encode($verifySchema(), JSON_THROW_ON_ERROR);
    foreach (['2026_09_08_000010_harden_wald_evidence_comparisons.php', '2026_09_08_000009_create_wald_knowledge.php'] as $file) {
        $migration = require database_path('migrations/'.$file);
        $refused = false;
        try {
            $migration->down();
        } catch (RuntimeException $e) {
            $refused = str_starts_with($e->getMessage(), 'Refusing rollback');
        }
        $check($refused, 'Populated rollback was not refused.');
        $check(json_encode($verifySchema(), JSON_THROW_ON_ERROR) === $schemaBefore, 'Populated rollback changed schema.');
    }
    echo json_encode(['result' => 'PASS', 'mysql' => DB::selectOne('SELECT VERSION() AS v')->v,
        'baseline_migrations' => count($baseline), 'additive_migrations' => 2, 'existing_rows_preserved' => 2,
        'empty_rollback_reapply' => 'PASS', 'populated_rollback' => 'REFUSED_BEFORE_DDL',
        'wald_tables' => 8, 'triggers' => 16], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    WaldFixtures::cleanup();
}
