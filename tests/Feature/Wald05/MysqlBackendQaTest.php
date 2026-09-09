<?php

use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;
use Tests\Support\Wald05QaProfiles;
use Tests\Support\Wald05Race;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable WALD05 MySQL 8.4 QA.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald05_')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
        throw new RuntimeException('disposable_mysql84_required');
    }
    config(['wald_import.enabled' => true]);
});
afterEach(fn () => B::$callBase = 1001);

it('W5Q bounds recognized server-signalled commit retries with exact receipt recovery', function ($state, $number, $failures, $expected, $success) {
    [$actor, $initial] = F::owner();
    $scope = new KnowledgeScope($initial->organisationId, $initial->siteId, 'retry-'.F::command(), 'family-v1');
    B::$callBase = 100000 + $scope->siteId * 1000;
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    $command = F::command();
    $attempts = 0;
    $enabled = true;
    DB::connection()->beforeExecuting(function ($sql) use (&$attempts, &$enabled, $state, $number, $failures) {
        if ($enabled && str_starts_with(strtolower($sql), 'insert into `wald_import_receipts`') && ++$attempts <= $failures) {
            $message = match ($number) {
                1213 => 'Deadlock found when trying to get lock',
                1205 => 'Lock wait timeout exceeded; try restarting transaction',
                default => 'QA server-signalled nontransient failure',
            };
            DB::unprepared("SIGNAL SQLSTATE '{$state}' SET MYSQL_ERRNO = {$number}, MESSAGE_TEXT = '{$message}'");
        }
    });
    $commit = fn () => (new ImportReview)->commit($actor, $scope, $preview['run'], $preview['preview'], $preview['hash'], $command);
    try {
        if ($success) {
            $receipt = $commit();
            // The client lost the first result: retry the same command and a new command.
            expect($commit())->toBe($receipt)->and(B::commit($actor, $scope, $preview))->toBe($receipt);
        } else {
            expect($commit)->toThrow(QueryException::class);
        }
    } finally {
        $enabled = false;
    }
    $run = DB::table('wald_import_runs')->where('uuid', $preview['run'])->firstOrFail();
    expect($attempts)->toBe($expected)
        ->and(DB::table('wald_import_receipts')->where('run_id', $run->id)->count())->toBe($success ? 1 : 0)
        ->and(DB::table('wald_visit_observations')->where('run_id', $run->id)->count())->toBe($success ? 7 : 0)
        ->and(DB::table('projected_plots')->where('site_id', $scope->siteId)->count())->toBe($success ? 7 : 0);
})->with([['40001', 1213, 2, 3, true], ['HY000', 1205, 2, 3, true], ['40001', 1213, 99, 3, false], ['45000', 1644, 99, 1, false]]);

it('W5Q preserves binary protected identities and verifies short schema names', function () {
    [$actor, $initial] = F::owner();
    $scope = new KnowledgeScope($initial->organisationId, $initial->siteId, 'schema-'.F::command(), 'family-v1');
    B::$callBase = 100000 + $scope->siteId * 1000;
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    B::commit($actor, $scope, $preview);
    $run = DB::table('wald_import_runs')->where('uuid', $preview['run'])->firstOrFail();
    foreach (['source_namespace' => strtoupper($scope->namespace), 'uploader_name' => $run->uploader_name.' ', 'confirmation' => strtolower($run->confirmation)] as $field => $value) {
        expect(fn () => DB::table('wald_import_runs')->where('id', $run->id)->update([$field => $value]))->toThrow(QueryException::class);
    }
    $long = DB::select('SELECT CONSTRAINT_NAME FROM information_schema.table_constraints WHERE CONSTRAINT_SCHEMA=DATABASE() AND CHAR_LENGTH(CONSTRAINT_NAME)>64');
    expect($long)->toBe([])->and(DB::select("SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'w5b_%'"))->toHaveCount(19);
});

it('W5Q races independent claims actual profile receipts and cross-family visit writers', function (string $scenario) {
    for ($i = 0; $i < 20; $i++) {
        [$actor, $initial] = F::owner();
        $scope = new KnowledgeScope($initial->organisationId, $initial->siteId, 'qa-race-'.F::command(), 'family-v1');
        B::$callBase = 100000 + $scope->siteId * 1000;
        B::binding($actor, $scope);
        $second = $actor->replicate();
        $second->email = F::command().'@example.test';
        $second->save();
        $scopes = [$scope, $scope];
        if ($scenario === 'claim') {
            $run = (new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command());
            $operations = [['claim', [$run['run'], 0, F::command()]], ['claim', [$run['run'], 0, F::command()]]];
        } elseif ($scenario === 'actual-profile-receipt') {
            [$a, $profile] = Wald05QaProfiles::reviewed($actor, $scope);
            $operations = [['commit', [$a['run'], $a['preview'], $a['hash'], F::command()]], ['profile_revoke', [$profile->uuid, $profile->lock_version, 'Race revocation.', F::command()]]];
        } else {
            $scopes[1] = new KnowledgeScope($scope->organisationId, $scope->siteId, $scope->namespace, 'other-family');
            $a = B::reviewed($actor, $scope);
            $b = B::reviewed($second, $scopes[1], [0 => ['VS' => '99.000']]);
            $operations = [['commit', [$a['run'], $a['preview'], $a['hash'], F::command()]], ['commit', [$b['run'], $b['preview'], $b['hash'], F::command()]]];
        }
        $results = Wald05Race::run([$actor, $second], $scope, $operations, $scopes);
        foreach ($results as $result) {
            if (! $result['ok']) {
                expect($result['exception'])->toBe(ImportConflict::class);
            }
        }
        if ($scenario === 'actual-profile-receipt') {
            expect($results[1]['ok'])->toBeTrue();
            expect(DB::table('wald_import_receipts')->whereIn('run_id', DB::table('wald_import_runs')->select('id')->where('site_id', $scope->siteId))->count())->toBe($results[0]['ok'] ? 1 : 0);
        } else {
            expect(collect($results)->where('ok', true)->count())->toBe(1);
        }
        if ($scenario === 'cross-family') {
            expect(DB::table('wald_source_visits')->where('site_id', $scope->siteId)->count())->toBe(7)
                ->and(DB::table('wald_visit_observations')->whereIn('visit_id', DB::table('wald_source_visits')->select('id')->where('site_id', $scope->siteId))->count())->toBe(7);
        }
    }
})->with(['claim', 'actual-profile-receipt', 'cross-family']);
