<?php

use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\SourceBindingService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\Wald04Fixtures as F;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable WALD05 MySQL 8.4.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald05_')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
        throw new RuntimeException('disposable_wald05_mysql84_required');
    }
    config(['wald_import.enabled' => true]);
});

function w5FoundationRace(array $actors, $scope, array $operations): array
{
    $directory = storage_path('framework/testing/wald05-race-'.F::command());
    mkdir($directory, 0700, true);
    $db = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1',
        'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
    $processes = [];
    try {
        foreach ($operations as $index => [$operation, $arguments]) {
            $input = ['actor' => $actors[$index]->id, 'scope' => [$scope->organisationId, $scope->siteId, $scope->namespace, $scope->family],
                'operation' => $operation, 'arguments' => $arguments, 'ready' => $directory.'/ready'.$index, 'barrier' => $directory.'/go'];
            $process = new Process([PHP_BINARY, base_path('tests/Support/Wald05ConcurrencyWorker.php'), base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], base_path(), $environment);
            $process->setTimeout(35)->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/ready0') || ! is_file($directory.'/ready1')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('wald05_workers_not_ready');
            }
            usleep(10000);
        }
        touch($directory.'/go');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('wald05_worker_failed');
            }
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach (['ready0', 'ready1', 'go'] as $name) {
            if (is_file($directory.'/'.$name)) {
                unlink($directory.'/'.$name);
            }
        }
        rmdir($directory);
    }
}

it('serializes binding lifecycle mutations across ten real two-connection races', function (string $scenario) {
    for ($iteration = 0; $iteration < 10; $iteration++) {
        [$first, $scope] = F::owner();
        $second = $first->replicate();
        $second->uuid = F::command();
        $second->email = F::command().'@example.test';
        $second->save();
        $service = new SourceBindingService;
        $name = 'Synthetic '.F::command();
        $draft = $service->draft($first, $scope, 'EXACT_SITE_NAME', $name, 'Initial.', F::command());
        $activation = [$draft['binding'], 1, $draft['definition_hash'], $draft['epoch'], 'Race.', F::command()];
        if ($scenario === 'activate-activate') {
            $operations = [['activate', $activation], ['activate', [...array_slice($activation, 0, 5), F::command()]]];
        } elseif ($scenario === 'exact-command-replay') {
            $second = $first;
            $operations = [['activate', $activation], ['activate', $activation]];
        } else {
            $active = $service->activate($first, $scope, ...$activation);
            if ($scenario === 'draft-draft') {
                $args = ['EXACT_SITE_NAME', $name, 'Successor.', F::command(), $active['epoch']];
                $operations = [['draft', $args], ['draft', ['EXACT_SITE_NAME', $name, 'Other successor.', F::command(), $active['epoch']]]];
            } else {
                $next = $service->draft($first, $scope, 'EXACT_SITE_NAME', $name, 'Successor.', F::command(), $active['epoch']);
                $operations = [['activate', [$draft['binding'], 2, $next['definition_hash'], $next['epoch'], 'Supersede.', F::command()]],
                    ['revoke', [$draft['binding'], $next['epoch'], 'Revoke.', F::command()]]];
            }
        }
        $results = w5FoundationRace([$first, $second], $scope, $operations);
        expect(collect($results)->where('ok', true)->count())->toBe($scenario === 'exact-command-replay' ? 2 : 1);
        foreach ($results as $result) {
            if (! $result['ok']) {
                expect($result['exception'])->toBe(ImportConflict::class);
            }
        }
        if ($scenario === 'exact-command-replay') {
            expect($results[0]['result'])->toBe($results[1]['result']);
        }
    }
})->with(['activate-activate', 'exact-command-replay', 'draft-draft', 'activate-revoke']);
