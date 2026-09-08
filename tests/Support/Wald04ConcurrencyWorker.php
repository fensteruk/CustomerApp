<?php

use App\Models\User;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\RevokeProfile;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\Actions\UseProfile;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (app()->environment() !== 'testing' || DB::getDriverName() !== 'mysql'
        || config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald04_')) {
        throw new RuntimeException('disposable_wald04_database_required');
    }
    $input = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $deadline = microtime(true) + 20;
    file_put_contents($input['ready'], 'ready');
    while (! file_exists($input['barrier'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('barrier_timeout');
        }
        usleep(10000);
    }
    $actor = User::query()->findOrFail($input['actor']);
    $scope = new KnowledgeScope(...$input['scope']);
    $action = match ($input['operation']) {
        'answer' => new AnswerClarification, 'activate' => new ActivateProfile,
        'revoke' => new RevokeProfile, 'reuse' => new UseProfile, 'draft' => new SaveProfileDraft,
        default => throw new RuntimeException('unknown_test_operation'),
    };
    $result = $action->handle($actor, $scope, ...$input['arguments']);
    echo json_encode(['ok' => true, 'uuid' => $result->uuid], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'exception' => get_class($exception)], JSON_THROW_ON_ERROR);
}
