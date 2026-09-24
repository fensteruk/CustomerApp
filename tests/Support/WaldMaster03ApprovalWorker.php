<?php

use App\Models\User;
use App\SourceImport\Integration\ApproveMasterHierarchyProposal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
        || config('database.connections.mysql.host') !== '127.0.0.1'
        || DB::connection()->getDatabaseName() !== 'customerapp_wald_master03'
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
        throw new RuntimeException('disposable_master03_mysql84_required');
    }
    $input = json_decode(base64_decode($argv[1]), true, flags: JSON_THROW_ON_ERROR);
    $testing = realpath(storage_path('framework/testing'));
    foreach (['ready', 'barrier'] as $key) {
        $parent = realpath(dirname($input[$key]));
        if (! $testing || ! $parent || ! str_starts_with($parent.DIRECTORY_SEPARATOR,
            $testing.DIRECTORY_SEPARATOR.'wald-master03-race-')) {
            throw new RuntimeException('invalid_master03_barrier');
        }
    }
    config(['wald_import.pilot_available' => true]);
    file_put_contents($input['ready'], 'ready');
    $deadline = microtime(true) + 20;
    while (! file_exists($input['barrier'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('master03_barrier_timeout');
        }
        usleep(10_000);
    }
    $result = app(ApproveMasterHierarchyProposal::class)->handle(
        User::query()->findOrFail($input['actor']), $input['upload'], $input['source'],
        $input['manifest'], (int) $input['epoch'], $input['outcome'], $input['customer'], $input['site'], $input['command'],
    );
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'exception' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
