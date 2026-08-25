<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RejectAlternativeCallOffDateAction;
use App\Data\SourceRecord;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use App\Models\User;
use App\Services\SourceProjectionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$script = $argv[0];
$operation = $argv[1] ?? '';
$arguments = array_slice($argv, 2);
$barrier = array_pop($arguments);

try {
    $deadline = microtime(true) + 20;
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('The MySQL concurrency barrier was not released.');
        }

        usleep(10_000);
    }

    match ($operation) {
        'agree' => app(AgreeRequestedCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1])),
        'propose' => app(ProposeAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), $arguments[2]),
        'accept' => app(AcceptAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), mysqlGateProposal($arguments[2])),
        'reject' => app(RejectAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), mysqlGateProposal($arguments[2]), 'The alternative is not suitable.'),
        'source-complete' => mysqlGateCompleteSourceService($arguments[0]),
        'source-missing' => mysqlGateMarkSourceMissing($arguments[0]),
        default => throw new InvalidArgumentException("Unknown MySQL gate operation: {$operation}"),
    };

    mysqlGateResult(true, $operation);
} catch (Throwable $exception) {
    mysqlGateResult(false, $operation, $exception);
}

function mysqlGateUser(string $id): User
{
    return User::query()->findOrFail($id);
}

function mysqlGateRequest(string $id): CallOffRequest
{
    return CallOffRequest::query()->findOrFail($id);
}

function mysqlGateProposal(string $id): CallOffDateProposal
{
    return CallOffDateProposal::query()->findOrFail($id);
}

function mysqlGateCompleteSourceService(string $serviceId): void
{
    $service = ProjectedPlotService::query()->with('projectedPlot.site')->findOrFail($serviceId);
    $site = $service->projectedPlot->site;

    app(SourceProjectionImportService::class)->import($site->external_source, [
        new SourceRecord(
            $service->source_call_number,
            $site->external_identifier,
            $service->projectedPlot->plot_reference,
            'PC1',
            null,
            CarbonImmutable::today(),
            [],
            now(),
        ),
    ], 'mysql-gate-completion');
}

function mysqlGateMarkSourceMissing(string $serviceId): void
{
    DB::transaction(function () use ($serviceId): void {
        $service = ProjectedPlotService::query()->whereKey($serviceId)->lockForUpdate()->firstOrFail();
        $service->update(['source_present' => false, 'source_missing_since' => now()]);

        // Keep the source row lock long enough to exercise a real competing database action.
        usleep(300_000);
    });
}

function mysqlGateResult(bool $ok, string $operation, ?Throwable $exception = null): never
{
    echo json_encode([
        'ok' => $ok,
        'operation' => $operation,
        'exception' => $exception === null ? null : $exception::class,
        'message' => $exception === null ? null : $exception->getMessage(),
    ], JSON_THROW_ON_ERROR);

    exit(0);
}
