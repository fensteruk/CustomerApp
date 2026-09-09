<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

final class Wald05Race
{
    public static function run(array $actors, $scope, array $operations): array
    {
        $directory = storage_path('framework/testing/wald05-race-'.Wald04Fixtures::command());
        mkdir($directory, 0700, true);
        $db = config('database.connections.mysql');
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
        $processes = [];
        try {
            foreach ($operations as $i => [$operation, $arguments]) {
                $input = ['actor' => $actors[$i]->id, 'scope' => [$scope->organisationId, $scope->siteId, $scope->namespace, $scope->family],
                    'operation' => $operation, 'arguments' => $arguments, 'ready' => $directory.'/ready'.$i, 'barrier' => $directory.'/go'];
                $process = new Process([PHP_BINARY, base_path('tests/Support/Wald05ConcurrencyWorker.php'), base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], base_path(), $env);
                $process->setTimeout(40)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 25;
            while (! is_file($directory.'/ready0') || ! is_file($directory.'/ready1')) {
                if (microtime(true) > $deadline) {
                    throw new \RuntimeException('workers_not_ready');
                } usleep(10000);
            }
            touch($directory.'/go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('worker_failed');
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
}
