<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ApproveMasterHierarchyProposal;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\PilotImportWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable MASTER03 MySQL 8.4.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1'
        || DB::connection()->getDatabaseName() !== 'customerapp_wald_master03'
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
        throw new RuntimeException('disposable_master03_mysql84_required');
    }
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
});

afterEach(function (): void {
    if (DB::getDriverName() === 'mysql') {
        $storage = Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')]);
        foreach (DB::table('wald_pilot_uploads')->pluck('storage_key') as $key) {
            $storage->delete($key);
        }
    }
});

function master03Race(string $customer, string $site, string $date): array
{
    $offices = [User::factory()->create(['customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true, 'is_preview_user' => false]),
        User::factory()->create(['customer_organisation_id' => null,
            'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
            'is_active' => true, 'is_preview_user' => false])];
    $code = 'RACE-'.$date;
    $csv = "CustomerNo,Call No.,Site Name,Plot Ref,Call Type,Complete,VS\n"
        .$code.",9911,Description,{$customer} - {$site} - Plot 1,PC1,No,2\n";
    $pilot = (new PilotImportWorkflow)->upload($offices[0],
        UploadedFile::fake()->createWithContent('race.csv', $csv),
        new ExportOrder($date, 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    $source = $pilot['sources'][0];
    $outcome = $source['resolution']['state'];
    $directory = storage_path('framework/testing/wald-master03-race-'.Str::uuid());
    mkdir($directory, 0700, true);
    $db = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => (string) $db['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
    $processes = [];
    try {
        foreach ($offices as $index => $office) {
            $input = ['actor' => $office->id, 'upload' => $pilot['upload'], 'source' => $source['hash'],
                'manifest' => $upload->source_manifest_hash, 'epoch' => (int) $upload->epoch,
                'outcome' => $outcome, 'customer' => $source['resolution']['customer'],
                'site' => $source['resolution']['site'], 'command' => (string) Str::uuid(),
                'ready' => $directory.'/ready'.$index, 'barrier' => $directory.'/go'];
            $process = new Process([PHP_BINARY, base_path('tests/Support/WaldMaster03ApprovalWorker.php'),
                base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], base_path(), $environment);
            $process->setTimeout(40)->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/ready0') || ! is_file($directory.'/ready1')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('master03_workers_not_ready');
            }
            usleep(10_000);
        }
        touch($directory.'/go');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->getExitCode())->toBe(0);
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach (['ready0', 'ready1', 'go'] as $file) {
            if (is_file($directory.'/'.$file)) {
                unlink($directory.'/'.$file);
            }
        }
        rmdir($directory);
    }
}

it('allows one new customer and site approval under two simultaneous Office sessions', function (): void {
    $results = master03Race('Race New Customer', 'Race New Site', '2099-12-01');
    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false)->first()['message'])->toBe('proposal_stale_refresh')
        ->and(CustomerOrganisation::query()->where('name', 'Race New Customer')->count())->toBe(1)
        ->and(Site::query()->where('name', 'Race New Site')->count())->toBe(1)
        ->and(DB::table('wald_source_bindings')->where('source_identity', 'RACE-2099-12-01')->count())->toBe(1)
        ->and(DB::table('wald_pilot_events')->where('pilot_upload_id', DB::table('wald_pilot_uploads')->where('export_date', '2099-12-01')->value('id'))
            ->where('action', 'pilot_hierarchy_creation_approved')->count())->toBe(1);
});

it('allows one new site approval beneath an existing customer under a race', function (): void {
    $customer = CustomerOrganisation::factory()->create(['name' => 'Race Existing Customer', 'is_active' => true]);
    $results = master03Race('Race Existing Customer', 'Race Second Site', '2099-12-02');
    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false)->first()['message'])->toBe('proposal_stale_refresh')
        ->and(CustomerOrganisation::query()->where('name', 'Race Existing Customer')->count())->toBe(1)
        ->and(Site::query()->where('customer_organisation_id', $customer->id)->where('name', 'Race Second Site')->count())->toBe(1)
        ->and(DB::table('wald_source_bindings')->where('source_identity', 'RACE-2099-12-02')->count())->toBe(1)
        ->and(DB::table('wald_pilot_events')->where('pilot_upload_id', DB::table('wald_pilot_uploads')->where('export_date', '2099-12-02')->value('id'))
            ->where('action', 'pilot_hierarchy_creation_approved')->count())->toBe(1);
});

it('rolls back a new customer and site when the binding write fails on MySQL', function (): void {
    $office = User::factory()->create(['customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true, 'is_preview_user' => false]);
    $csv = "CustomerNo,Call No.,Site Name,Plot Ref,Call Type,Complete,VS\n"
        ."ROLLBACK-MYSQL,9971,Description,Rollback MySQL Customer - Rollback MySQL Site - Plot 1,PC1,No,2\n";
    $pilot = (new PilotImportWorkflow)->upload($office,
        UploadedFile::fake()->createWithContent('rollback.csv', $csv),
        new ExportOrder('2099-12-03', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();
    DB::statement("CREATE TRIGGER master03_fail_binding BEFORE INSERT ON wald_binding_versions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced binding failure'");
    try {
        expect(fn () => app(ApproveMasterHierarchyProposal::class)->handle($office, $pilot['upload'],
            $pilot['sources'][0]['hash'], $upload->source_manifest_hash, (int) $upload->epoch,
            'NEW_CUSTOMER_AND_SITE', 'Rollback MySQL Customer', 'Rollback MySQL Site', (string) Str::uuid()))
            ->toThrow(ImportConflict::class, 'proposal_stale_refresh');
        expect(CustomerOrganisation::query()->where('name', 'Rollback MySQL Customer')->count())->toBe(0)
            ->and(Site::query()->where('name', 'Rollback MySQL Site')->count())->toBe(0)
            ->and(DB::table('wald_source_bindings')->where('source_identity', 'ROLLBACK-MYSQL')->count())->toBe(0)
            ->and(DB::table('wald_pilot_events')->where('pilot_upload_id', $upload->id)
                ->where('action', 'pilot_hierarchy_creation_approved')->count())->toBe(0);
    } finally {
        DB::statement('DROP TRIGGER IF EXISTS master03_fail_binding');
    }
});
