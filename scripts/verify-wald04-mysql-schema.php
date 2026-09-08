<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald04_')) {
    throw new RuntimeException('Only an explicitly disposable local WALD04 MySQL database is allowed.');
}
$version = DB::selectOne('SELECT VERSION() AS v')->v;
if (! str_starts_with($version, '8.4.')) {
    throw new RuntimeException('MySQL 8.4 required.');
}
$tables = DB::select("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'wald_%'");
$triggers = DB::select("SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'w4_%'");
$invalid = DB::select('SELECT CONSTRAINT_NAME FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND CHAR_LENGTH(CONSTRAINT_NAME)>64');
if (count($tables) !== 8 || count($triggers) !== 16 || $invalid !== []) {
    throw new RuntimeException('WALD04 schema contract mismatch.');
}
$fk = DB::select("SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.key_column_usage WHERE constraint_schema=DATABASE() AND constraint_name IN ('w4_ctx_site_fk','w4_pr_site_fk','w4_pr_active_fk','w4_use_ver_fk')");
if (count($fk) !== 4) {
    throw new RuntimeException('Missing composite containment/version foreign key.');
}
echo json_encode(['mysql' => $version, 'database' => DB::connection()->getDatabaseName(), 'tables' => count($tables),
    'immutability_triggers' => count($triggers), 'composite_relationships_checked' => count($fk), 'overlong_constraints' => count($invalid), 'result' => 'PASS'], JSON_THROW_ON_ERROR).PHP_EOL;
