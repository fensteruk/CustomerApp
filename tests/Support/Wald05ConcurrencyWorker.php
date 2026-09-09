<?php

use App\Models\User;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\RevokeProfile;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
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
    $service = match ($input['operation']) {
        'commit' => new ImportReview,
        'claim' => new ImportAnalysis,
        'profile_revoke' => new RevokeProfile,
        default => new SourceBindingService,
    };
    if (! in_array($input['operation'], ['draft', 'activate', 'revoke', 'commit', 'claim', 'profile_revoke', 'projection_change'], true)) {
        throw new RuntimeException('invalid_test_operation');
    }
    if ($input['operation'] === 'projection_change') {
        $result = DB::transaction(function () use ($input, $scope) {
            [$id, $epoch] = $input['arguments'];
            $service = DB::table('projected_plot_services')->where('id', $id)->whereIn('projected_plot_id', DB::table('projected_plots')->select('id')->where('site_id', $scope->siteId))->lockForUpdate()->firstOrFail();
            if ((int) $service->wald_epoch !== $epoch) {
                throw new ImportConflict('projection_epoch_conflict');
            }
            DB::table('projected_plot_services')->where('id', $id)->update(['source_updated_at' => now()]);

            return ['changed' => true];
        }, 3);
    } else {
        $method = $input['operation'] === 'profile_revoke' ? 'handle' : $input['operation'];
        $result = $service->{$method}($actor, $scope, ...$input['arguments']);
        if ($result instanceof Model) {
            $result = ['uuid' => $result->uuid];
        }
    }
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'exception' => $error::class, 'code' => $error instanceof ImportConflict ? $error->getMessage() : null], JSON_THROW_ON_ERROR);
}
