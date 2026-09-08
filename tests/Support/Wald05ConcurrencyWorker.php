<?php

use App\Models\User;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
        || config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald05_')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
        throw new RuntimeException('disposable_wald05_mysql84_required');
    }
    config(['wald_import.enabled' => true]);
    $input = json_decode(base64_decode($argv[1]), true, flags: JSON_THROW_ON_ERROR);
    $directory = realpath(storage_path('framework/testing'));
    foreach (['ready', 'barrier'] as $key) {
        $parent = realpath(dirname($input[$key]));
        if ($parent === false || ! str_starts_with($parent.DIRECTORY_SEPARATOR, $directory.DIRECTORY_SEPARATOR.'wald05-race-')) {
            throw new RuntimeException('invalid_test_barrier');
        }
    }
    file_put_contents($input['ready'], 'ready');
    $deadline = microtime(true) + 20;
    while (! file_exists($input['barrier'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('barrier_timeout');
        }
        usleep(10000);
    }
    $actor = User::query()->findOrFail($input['actor']);
    $scope = new KnowledgeScope(...$input['scope']);
    $service = new SourceBindingService;
    if (! in_array($input['operation'], ['draft', 'activate', 'revoke'], true)) {
        throw new RuntimeException('invalid_test_operation');
    }
    $result = $service->{$input['operation']}($actor, $scope, ...$input['arguments']);
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'exception' => $error::class, 'code' => $error instanceof ImportConflict ? $error->getMessage() : null], JSON_THROW_ON_ERROR);
}
