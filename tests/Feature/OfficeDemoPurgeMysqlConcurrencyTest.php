<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Services\DemoPurgeImpact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable CLEANUP02 MySQL 8.4.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_cleanup02')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
        throw new RuntimeException('disposable_cleanup02_mysql84_required');
    }
});

it('rejects purge after a concurrent import changes state while the site lock is held', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $stream = DB::table('wald_import_streams')->insertGetId([
        'identity_hash' => hash('sha256', (string) Str::uuid()),
        'source_namespace' => 'cleanup02', 'workbook_family' => 'race',
    ]);
    $run = DB::table('wald_import_runs')->insertGetId([
        'uuid' => (string) Str::uuid(), 'stream_id' => $stream,
        'customer_organisation_id' => $customer->id, 'site_id' => $site->id,
        'source_namespace' => 'cleanup02', 'workbook_family' => 'race',
        'uploader_id' => $office->id, 'uploader_name' => $office->name,
        'storage_key' => (string) Str::uuid().'.xlsx', 'original_name' => 'fictional.xlsx',
        'format' => 'xlsx', 'mime' => 'application/zip', 'byte_count' => 1,
        'workbook_hash' => str_repeat('a', 64), 'export_date' => '2026-09-22',
        'export_slot' => 'MORNING', 'export_order' => '20260922AM',
        'confirmation' => 'Disposable race fixture', 'provenance' => 'OFFICE_DECLARED',
        'coverage' => 'PARTIAL_FILTERED_EXPORT', 'state' => 'READY_TO_COMMIT',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $fingerprint = app(DemoPurgeImpact::class)->site($site)['fingerprint'];
    $worker = new Process([PHP_BINARY, base_path('tests/Support/DemoPurgeMysqlWorker.php'),
        $office->id, $customer->id, $site->id, $fingerprint], base_path());

    try {
        DB::beginTransaction();
        DB::table('sites')->where('id', $site->id)->lockForUpdate()->first();
        DB::table('wald_import_runs')->where('id', $run)->update(['state' => 'COMMITTING']);
        $worker->setTimeout(30)->start();
        usleep(500_000);
        DB::commit();
        $worker->wait();

        expect($worker->getExitCode())->toBe(0)
            ->and(json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['outcome'])->toBe('stale_or_blocked')
            ->and(Site::query()->whereKey($site->id)->exists())->toBeTrue()
            ->and(DB::table('wald_import_runs')->where('id', $run)->value('state'))->toBe('COMMITTING')
            ->and(DB::table('demo_purge_gate')->count())->toBe(0);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
});
