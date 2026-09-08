<?php

use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable MySQL 8.4 WALD04 gate.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald04_')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
        throw new RuntimeException('disposable_mysql_84_required');
    }
});
afterEach(fn () => WaldFixtures::cleanup());

it('bounds transaction retries for real server-signalled failures without duplicate history', function ($state, $number, $message, $failures, $expectedAttempts, $success) {
    [$office, $scope] = F::owner();
    $snapshot = F::snapshot();
    $command = F::command();
    $attempts = 0;
    // Actual MySQL errors at the audit boundary, not evidence of a naturally occurring deadlock.
    KnowledgeEvent::creating(function () use (&$attempts, $failures, $state, $number, $message) {
        if (++$attempts <= $failures) {
            DB::unprepared("SIGNAL SQLSTATE '{$state}' SET MYSQL_ERRNO = {$number}, MESSAGE_TEXT = '{$message}'");
        }
    });
    try {
        $run = fn () => (new RegisterContext)->handle($office, $scope, $snapshot, $command);
        if ($success) {
            $result = $run();
            expect($run()->uuid)->toBe($result->uuid);
        } else {
            expect($run)->toThrow(QueryException::class);
        }
        expect($attempts)->toBe($expectedAttempts)
            ->and(KnowledgeContext::query()->where($scope->columns())->count())->toBe($success ? 1 : 0)
            ->and(KnowledgeEvent::query()->where('actor_id', $office->id)->where('command_uuid', $command)->count())->toBe($success ? 1 : 0);
    } finally {
        KnowledgeEvent::flushEventListeners();
    }
})->with([
    ['40001', 1213, 'Deadlock found when trying to get lock', 2, 3, true],
    ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction', 2, 3, true],
    ['40001', 1213, 'Deadlock found when trying to get lock', 99, 3, false],
    ['45000', 1644, 'QA non-transient audit failure', 99, 1, false],
]);
