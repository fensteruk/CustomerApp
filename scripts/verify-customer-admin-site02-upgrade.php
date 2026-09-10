<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'mysql'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_admin_site02_upgrade')
    || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
    throw new RuntimeException('Disposable ADMIN-SITE02 MySQL 8.4 upgrade database required.');
}

$migration = require __DIR__.'/../database/migrations/2026_09_09_000014_add_customer_site_administration.php';
$tablesBefore = collect(DB::select('SHOW TABLES'))->map(fn (object $row): string => (string) array_values((array) $row)[0])->sort()->values()->all();
$migration->down();

$customerId = DB::table('customer_organisations')->insertGetId([
    'name' => 'ADMIN-SITE02 Legacy Customer',
    'created_at' => now(),
    'updated_at' => now(),
]);
$siteId = DB::table('sites')->insertGetId([
    'customer_organisation_id' => $customerId,
    'name' => 'ADMIN-SITE02 Legacy Site',
    'location' => 'Legacy location',
    'external_source' => null,
    'external_identifier' => null,
    'created_at' => now(),
    'updated_at' => now(),
]);

$migration->up();

$customer = DB::table('customer_organisations')->where('id', $customerId)->first();
$site = DB::table('sites')->where('id', $siteId)->first();
$tablesAfter = collect(DB::select('SHOW TABLES'))->map(fn (object $row): string => (string) array_values((array) $row)[0])->sort()->values()->all();

$checks = [
    'table_set_preserved' => $tablesBefore === $tablesAfter,
    'customer_preserved' => $customer?->name === 'ADMIN-SITE02 Legacy Customer',
    'customer_uuid_backfilled' => is_string($customer?->uuid) && $customer->uuid !== '',
    'customer_active_backfilled' => (int) ($customer?->is_active ?? 0) === 1,
    'customer_version_backfilled' => (int) ($customer?->lock_version ?? 0) === 1,
    'site_preserved' => $site?->name === 'ADMIN-SITE02 Legacy Site',
    'site_uuid_backfilled' => is_string($site?->uuid) && $site->uuid !== '',
    'site_active_backfilled' => (int) ($site?->is_active ?? 0) === 1,
    'site_version_backfilled' => (int) ($site?->lock_version ?? 0) === 1,
    'site_owner_preserved' => (int) ($site?->customer_organisation_id ?? 0) === $customerId,
    'source_reference_remains_optional' => $site?->external_source === null && $site?->external_identifier === null,
    'audit_table_available' => Schema::hasTable('administrative_audits'),
];

foreach ($checks as $name => $passed) {
    if (! $passed) {
        throw new RuntimeException("ADMIN-SITE02 upgrade check failed: {$name}");
    }
}

echo json_encode([
    'mysql_version' => DB::selectOne('SELECT VERSION() AS version')->version,
    'database' => DB::connection()->getDatabaseName(),
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
