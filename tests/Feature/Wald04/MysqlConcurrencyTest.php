<?php

use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeQueries;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
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

function wald04Race($actor, $scope, array $operations): array
{
    $directory = storage_path('framework/testing/wald04-race-'.F::command());
    mkdir($directory, 0700, true);
    $barrier = $directory.'/go';
    $processes = [];
    $db = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => $db['host'],
        'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
        'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
    try {
        foreach ($operations as $i => [$operation, $arguments]) {
            $input = ['actor' => $actor[$i]->id, 'scope' => [$scope->organisationId, $scope->siteId, $scope->namespace, $scope->family],
                'operation' => $operation, 'arguments' => $arguments, 'barrier' => $barrier, 'ready' => $directory.'/ready'.$i];
            $process = new Process([PHP_BINARY, base_path('tests/Support/Wald04ConcurrencyWorker.php'), base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], base_path(), $environment);
            $process->setTimeout(40)->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 25;
        while (! file_exists($directory.'/ready0') || ! file_exists($directory.'/ready1')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('workers_not_ready');
            }
            usleep(10000);
        }
        touch($barrier);
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('worker_process_failed');
            }
            $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach (glob($directory.'/*') as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
}

it('serializes competing answers without last-write-wins across ten MySQL races', function () {
    foreach (range(1, 10) as $iteration) {
        [$office, $scope] = F::owner();
        $second = $office->replicate();
        $second->email = F::command().'@example.test';
        $second->save();
        $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
        $q = (new KnowledgeQueries)->questions($office, $scope, $context->uuid)[0];
        $arguments = [$context->uuid, $q['uuid'], 0, $q['evidence']['candidates'][0]['id'], 'Concurrent answer.'];
        $results = wald04Race([$office, $second], $scope, [['answer', [...$arguments, F::command()]], ['answer', [...$arguments, F::command()]]]);
        expect(collect($results)->where('ok', true)->count())->toBe(1)
            ->and(collect($results)->where('ok', false)->first()['exception'])->toBe(KnowledgeConflict::class);
    }
});

it('serializes activation revoke and draft-use races with truthful receipts', function ($race) {
    foreach (range(1, 10) as $iteration) {
        [$office, $scope] = F::owner();
        $second = $office->replicate();
        $second->email = F::command().'@example.test';
        $second->save();
        [$origin, , $answer, $version, $profile] = $race === 'activate-activate' ? F::draft($office, $scope) : F::active($office, $scope);
        $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
        $activation = ['activate', [$profile->uuid, 1, $version->definition_hash, $profile->lock_version, 'Race activation.', F::command()]];
        $revoke = ['revoke', [$profile->uuid, $profile->lock_version, 'Race revoke.', F::command()]];
        $reuse = ['reuse', [$context->uuid, $profile->uuid, $profile->lock_version, F::command()]];
        $draft = ['draft', [$origin->uuid, $answer->uuid, F::command(), $profile->uuid, $profile->lock_version]];
        $operations = match ($race) {
            'activate-activate' => [$activation, ['activate', [$profile->uuid, 1, $version->definition_hash, $profile->lock_version, 'Second activation.', F::command()]]],
            'activate-revoke' => [$activation, $revoke], 'revoke-reuse' => [$revoke, $reuse], 'draft-reuse' => [$draft, $reuse],
        };
        $results = wald04Race([$office, $second], $scope, $operations);
        foreach ($results as $result) {
            if (! $result['ok']) {
                expect($result['exception'])->toBe(KnowledgeConflict::class);
            }
        }
        if (in_array($race, ['activate-activate', 'activate-revoke'], true)) {
            expect(collect($results)->where('ok', true)->count())->toBe(1);
        } else {
            expect($results[0]['ok'])->toBeTrue();
            if ($results[1]['ok']) {
                expect((new KnowledgeQueries)->receiptEligible($second, $scope, $results[1]['uuid']))->toBeFalse();
            }
        }
        expect($profile->fresh()->lock_version)->toBe($profile->lock_version + 1);
    }
})->with(['activate-activate', 'activate-revoke', 'revoke-reuse', 'draft-reuse']);

it('activates only one of two competing immutable versions across ten MySQL races', function () {
    foreach (range(1, 10) as $iteration) {
        [$office, $scope] = F::owner();
        $second = $office->replicate();
        $second->email = F::command().'@example.test';
        $second->save();
        [$origin, , $answer, $first, $profile] = F::draft($office, $scope);
        $next = (new SaveProfileDraft)->handle($office, $scope, $origin->uuid,
            $answer->uuid, F::command(), $profile->uuid, $profile->lock_version);
        $profile->refresh();
        $results = wald04Race([$office, $second], $scope, [
            ['activate', [$profile->uuid, $first->version, $first->definition_hash, $profile->lock_version, 'First version reviewed.', F::command()]],
            ['activate', [$profile->uuid, $next->version, $next->definition_hash, $profile->lock_version, 'Successor reviewed.', F::command()]],
        ]);
        expect(collect($results)->where('ok', true)->count())->toBe(1)
            ->and(collect($results)->where('ok', false)->first()['exception'])->toBe(KnowledgeConflict::class)
            ->and($profile->fresh()->active_version)->toBeIn([1, 2]);
    }
});
