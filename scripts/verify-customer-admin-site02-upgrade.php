<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

$now = now();
$customerId = DB::table('customer_organisations')->insertGetId([
    'name' => 'ADMIN-SITE02 Legacy Customer',
    'created_at' => $now,
    'updated_at' => $now,
]);
$siteId = DB::table('sites')->insertGetId([
    'customer_organisation_id' => $customerId,
    'name' => 'ADMIN-SITE02 Legacy Site',
    'location' => 'Legacy location',
    'external_source' => null,
    'external_identifier' => null,
    'created_at' => $now,
    'updated_at' => $now,
]);

$siteManagerRoleId = DB::table('portal_roles')->where('identifier', 'site_manager')->value('id');
$userId = DB::table('users')->insertGetId([
    'customer_organisation_id' => $customerId,
    'portal_role_id' => $siteManagerRoleId,
    'name' => 'ADMIN-SITE02 Legacy User',
    'email' => 'admin-site02-upgrade@example.test',
    'email_verified_at' => $now,
    'password' => Hash::make(Str::random(40)),
    'is_active' => true,
    'is_preview_user' => false,
    'created_at' => $now,
    'updated_at' => $now,
]);
DB::table('site_user_assignments')->insert([
    'user_id' => $userId,
    'site_id' => $siteId,
    'created_at' => $now,
    'updated_at' => $now,
]);

$plotId = DB::table('projected_plots')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'site_id' => $siteId,
    'external_source' => 'verified-upgrade-fixture',
    'external_identifier' => 'ADMIN-SITE02-PLOT-001',
    'plot_reference' => 'Plot 001',
    'is_completed' => false,
    'source_updated_at' => $now,
    'synchronised_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);
$plotServiceId = DB::table('projected_plot_services')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'projected_plot_id' => $plotId,
    'service_identifier' => 'windows',
    'source_call_number' => 'ADMIN-SITE02-CALL-001',
    'source_call_type' => 'upgrade-fixture',
    'source_job_stage' => 'preserved',
    'source_updated_at' => $now,
    'last_observed_at' => $now,
    'source_present' => true,
    'created_at' => $now,
    'updated_at' => $now,
]);
$batchId = DB::table('call_off_batches')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'site_id' => $siteId,
    'submitted_by_user_id' => $userId,
    'service_identifier' => 'windows',
    'requested_date' => '2026-10-08',
    'customer_response' => 'Preserve this production-shaped request.',
    'submitted_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);
$requestId = DB::table('call_off_requests')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'call_off_batch_id' => $batchId,
    'projected_plot_id' => $plotId,
    'projected_plot_service_id' => $plotServiceId,
    'service_identifier' => 'windows',
    'requested_date' => '2026-10-08',
    'normal_earliest_date' => '2026-10-08',
    'is_early_date_exception' => false,
    'agreed_date' => '2026-10-08',
    'customer_response' => 'Preserve this production-shaped request.',
    'status' => 'date_agreed',
    'active_conflict_key' => "{$plotServiceId}:windows",
    'created_at' => $now,
    'updated_at' => $now,
]);
DB::table('call_off_status_histories')->insert([
    'uuid' => (string) Str::uuid(),
    'call_off_request_id' => $requestId,
    'call_off_batch_id' => $batchId,
    'performed_by_user_id' => $userId,
    'sequence' => 1,
    'event_type' => 'date_agreed',
    'new_status' => 'date_agreed',
    'after_state' => json_encode(['agreed_date' => '2026-10-08'], JSON_THROW_ON_ERROR),
    'performed_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);
$negotiationId = DB::table('call_off_date_negotiations')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'call_off_request_id' => $requestId,
    'purpose' => 'amendment',
    'status' => 'date_agreed',
    'prior_agreed_date' => '2026-10-01',
    'requested_date' => '2026-10-08',
    'reason_code' => 'PROGRAMME_CHANGE',
    'reason_label' => 'Programme change',
    'requested_by_user_id' => $userId,
    'requester_name' => 'ADMIN-SITE02 Legacy User',
    'requester_role' => 'Site Manager',
    'is_urgent' => false,
    'is_early_date_exception' => false,
    'normal_earliest_date' => '2026-10-08',
    'resulting_agreed_date' => '2026-10-08',
    'opened_at' => $now->copy()->subMinute(),
    'closed_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);

$preservedCounts = collect([
    'users', 'site_user_assignments', 'projected_plots', 'projected_plot_services',
    'call_off_batches', 'call_off_requests', 'call_off_status_histories',
    'call_off_date_negotiations',
])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();

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
    'related_table_counts_preserved' => collect($preservedCounts)->every(
        fn (int $count, string $table): bool => DB::table($table)->count() === $count,
    ),
    'user_and_assignment_preserved' => DB::table('users')->where('id', $userId)->exists()
        && DB::table('site_user_assignments')->where('user_id', $userId)->where('site_id', $siteId)->exists(),
    'plot_and_service_preserved' => DB::table('projected_plots')->where('id', $plotId)->where('site_id', $siteId)->exists()
        && DB::table('projected_plot_services')->where('id', $plotServiceId)->where('projected_plot_id', $plotId)->exists(),
    'call_off_graph_preserved' => DB::table('call_off_batches')->where('id', $batchId)->where('site_id', $siteId)->exists()
        && DB::table('call_off_requests')->where('id', $requestId)->where('call_off_batch_id', $batchId)->where('status', 'date_agreed')->exists()
        && DB::table('call_off_status_histories')->where('call_off_request_id', $requestId)->where('event_type', 'date_agreed')->exists(),
    'sprint_3f_history_preserved' => DB::table('call_off_date_negotiations')->where('id', $negotiationId)
        ->where('call_off_request_id', $requestId)
        ->where('purpose', 'amendment')
        ->where('prior_agreed_date', '2026-10-01')
        ->where('resulting_agreed_date', '2026-10-08')
        ->exists(),
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
