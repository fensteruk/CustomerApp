<?php

use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;
use Tests\Support\Wald05Race;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable WALD05 MySQL 8.4.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1' || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald05_')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
        throw new RuntimeException('disposable_mysql84_required');
    }
    config(['wald_import.enabled' => true]);
});
afterEach(fn () => B::$callBase = 1001);

it('W5Q03 serializes audit outcomes without duplicate or contradictory history', function (string $scenario) {
    for ($i = 0; $i < 20; $i++) {
        [$actor, $initial] = F::owner();
        $scope = new KnowledgeScope($initial->organisationId, $initial->siteId, 'attempt-'.F::command(), 'family-v1');
        B::$callBase = 100000 + $scope->siteId * 1000;
        B::binding($actor, $scope);
        $preview = B::reviewed($actor, $scope);
        $actors = [$actor, $actor];
        $command = F::command();
        $args = [$preview['run'], $preview['preview'], $preview['hash'], $command];
        $operations = [['commit', $args], ['commit', $args]];
        if ($scenario !== 'same-command') {
            $actors[1] = $actor->replicate();
            $actors[1]->email = F::command().'@example.test';
            $actors[1]->save();
            $operations[1][1][3] = F::command();
        }
        if ($scenario === 'stale') {
            // Mutable current run epoch makes the immutable reviewed generation stale in both workers.
            DB::table('wald_import_runs')->where('uuid', $preview['run'])->increment('epoch');
        }
        if ($scenario === 'failure-success') {
            $operations[0][0] = 'commit_fail';
        }
        $results = Wald05Race::run($actors, $scope, $operations);
        $run = DB::table('wald_import_runs')->where('uuid', $preview['run'])->firstOrFail();
        $attempts = DB::table('wald_commit_attempts')->where('run_id', $run->id)->get();
        $outcomes = DB::table('wald_commit_attempt_outcomes')->whereIn('attempt_id', $attempts->pluck('id'))->get();
        expect($attempts)->toHaveCount($scenario === 'same-command' ? 1 : 2)->and($outcomes)->toHaveCount($attempts->count());
        expect(DB::table('wald_import_receipts')->where('run_id', $run->id)->count())->toBe($scenario === 'stale' ? 0 : 1);
        expect(DB::table('wald_visit_observations')->where('run_id', $run->id)->count())->toBe($scenario === 'stale' ? 0 : 7);
        if ($scenario === 'stale') {
            foreach ($results as $result) {
                expect($result['ok'])->toBeFalse()->and($result['exception'])->toBe(ImportConflict::class)->and($result['code'])->toBe('stale_preview_generation');
            }
            expect($outcomes->pluck('outcome')->unique()->all())->toBe(['STALE']);
        } elseif ($scenario === 'same-command') {
            expect($results[0]['ok'])->toBeTrue()->and($results[1]['ok'])->toBeTrue()->and($results[0]['result'])->toBe($results[1]['result']);
            expect($outcomes->first()->outcome)->toBe('SUCCEEDED');
        } else {
            expect($results[1]['ok'])->toBeTrue();
            if (! $results[0]['ok']) {
                expect($results[0]['exception'])->toBe(RuntimeException::class);
            }
            expect($outcomes->where('outcome', 'FAILED')->count())->toBe($results[0]['ok'] ? 0 : 1)
                ->and($outcomes->where('outcome', 'SUCCEEDED')->count())->toBe($results[0]['ok'] ? 2 : 1);
        }
    }
})->with(['stale', 'same-command', 'failure-success']);
