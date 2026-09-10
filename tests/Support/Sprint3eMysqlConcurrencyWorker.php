<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RejectAlternativeCallOffDateAction;
use App\Actions\CallOff\RequestCallOffAmendmentAction;
use App\Data\SourceRecord;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\PortalNotification;
use App\Models\ProjectedPlotService;
use App\Models\SourceProjectionEvent;
use App\Models\User;
use App\Services\SourceProjectionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommittedMysqlFixtureScope;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$script = $argv[0];
$operation = $argv[1] ?? '';
$arguments = array_slice($argv, 2);
$barrier = array_pop($arguments);
$delayMilliseconds = (int) array_pop($arguments);
$officeRaceWinner = getenv('MYSQL_GATE_OFFICE_RACE_WINNER') ?: null;
$officeRaceTrace = [];

try {
    if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'mysql') {
        throw new RuntimeException('Concurrency workers require an isolated MySQL testing environment.');
    }
    CommittedMysqlFixtureScope::assertSafe();
    config(['call_off_amendments.reasons' => ['test_reason' => 'Synthetic MySQL test reason']]);
    if ($officeRaceWinner !== null) {
        mysqlGateSynchronizeOfficeSnapshot($officeRaceWinner, $operation, $barrier);
    }
    $deadline = microtime(true) + 20;
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('The MySQL concurrency barrier was not released.');
        }

        usleep(10_000);
    }

    if ($delayMilliseconds > 0) {
        usleep($delayMilliseconds * 1000);
    }

    match ($operation) {
        'agree' => app(AgreeRequestedCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1])),
        'propose' => app(ProposeAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), $arguments[2]),
        'accept' => app(AcceptAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), mysqlGateProposal($arguments[2])),
        'reject' => app(RejectAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), mysqlGateProposal($arguments[2]), 'The alternative is not suitable.'),
        'amend-request' => app(RequestCallOffAmendmentAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1])->batch->site, mysqlGateRequest($arguments[1]), ['requested_date' => $arguments[2], 'reason_code' => 'test_reason'], $arguments[3]),
        'amend-agree' => app(AgreeRequestedCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), true, $arguments[2]),
        'amend-propose' => app(ProposeAlternativeCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]), $arguments[2], null, null, $arguments[3], true),
        'crash-after-agree' => mysqlGateCrashAfterAgreement($arguments),
        'source-complete' => mysqlGateCompleteSourceService($arguments[0]),
        'source-missing' => mysqlGateMarkSourceMissing($arguments[0]),
        default => throw new InvalidArgumentException("Unknown MySQL gate operation: {$operation}"),
    };

    if ($officeRaceWinner === $operation) {
        touch($barrier.'.office-winner-committed');
    }
    mysqlGateResult(true, $operation, arguments: $arguments);
} catch (Throwable $exception) {
    mysqlGateResult(false, $operation, $exception, $arguments);
}

/**
 * Force an earlier REPEATABLE READ snapshot in both genuine worker transactions.
 * The nominated winner commits before the loser's first aggregate locking read.
 * No application mocks or isolation-level changes are involved.
 */
function mysqlGateSynchronizeOfficeSnapshot(string $winner, string $operation, string $barrier): void
{
    if (! in_array($winner, ['amend-agree', 'amend-propose'], true)
        || ! in_array($operation, ['amend-agree', 'amend-propose'], true)) {
        throw new InvalidArgumentException('Office snapshot synchronization requires two Office decisions.');
    }
    $captured = false;
    DB::listen(function (QueryExecuted $query) use ($winner, $operation, $barrier, &$captured): void {
        global $officeRaceTrace;
        $officeRaceTrace[] = ['sql' => $query->sql, 'transaction_level' => DB::transactionLevel()];
        $normalized = str_replace(['`', '"'], '', strtolower($query->sql));
        if ($captured || DB::transactionLevel() === 0
            || ! str_starts_with($normalized, 'select projected_plot_service_id from call_off_requests')
            || str_contains($normalized, 'for update')) {
            return;
        }
        $captured = true;
        touch($barrier.'.office-snapshot-'.$operation);
        $other = $operation === 'amend-agree' ? 'amend-propose' : 'amend-agree';
        mysqlGateAwaitMarker($barrier.'.office-snapshot-'.$other);
        $officeRaceTrace[] = ['phase' => 'both_consistent_snapshots_established'];
        if ($operation !== $winner) {
            mysqlGateAwaitMarker($barrier.'.office-winner-committed');
            $officeRaceTrace[] = ['phase' => 'winner_committed_before_loser_aggregate_locks'];
        }
    });
}

function mysqlGateAwaitMarker(string $path): void
{
    $deadline = microtime(true) + 20;
    while (! file_exists($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('The synchronized Office race marker was not released.');
        }
        usleep(10_000);
        clearstatcache(true, $path);
    }
}

function mysqlGateCrashAfterAgreement(array $arguments): never
{
    app(AgreeRequestedCallOffDateAction::class)->handle(mysqlGateUser($arguments[0]), mysqlGateRequest($arguments[1]));
    exit(17); // Deliberate process failure after a real commit, used only by isolation regression.
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
            CarbonImmutable::now(),
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

/** @param array<int, string> $arguments */
function mysqlGateResult(bool $ok, string $operation, ?Throwable $exception = null, array $arguments = []): never
{
    global $officeRaceTrace, $officeRaceWinner;
    echo json_encode([
        'ok' => $ok,
        'operation' => $operation,
        'exception' => $exception === null ? null : $exception::class,
        'message' => $exception === null ? null : $exception->getMessage(),
        'sqlstate' => $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null,
        'driver_code' => $exception instanceof QueryException ? ($exception->errorInfo[1] ?? null) : null,
        'office_race_winner' => $officeRaceWinner,
        'office_race_trace' => $officeRaceTrace,
        'durable_state' => mysqlGateDurableState($operation, $arguments),
    ], JSON_THROW_ON_ERROR);

    exit(0);
}

/** @param array<int, string> $arguments
 * @return array<string, mixed>
 */
function mysqlGateDurableState(string $operation, array $arguments): array
{
    $requestId = match ($operation) {
        'agree', 'propose', 'accept', 'reject', 'amend-request', 'amend-agree', 'amend-propose' => $arguments[1] ?? null,
        'source-complete', 'source-missing' => ProjectedPlotService::query()
            ->find($arguments[0] ?? null)?->callOffRequests()
            ->orderBy('id')
            ->value('id'),
        default => null,
    };
    $request = $requestId === null ? null : CallOffRequest::query()->find($requestId);
    $service = match ($operation) {
        'source-complete', 'source-missing' => ProjectedPlotService::query()->find($arguments[0] ?? null),
        default => $request?->projectedPlotService,
    };

    if ($request === null || $service === null) {
        return ['request_found' => $request !== null, 'service_found' => $service !== null];
    }

    return [
        'service' => [
            'source_present' => $service->source_present,
            'source_completed' => $service->isSourceCompleted(),
        ],
        'request' => [
            'status' => $request->status->value,
            'agreed_date' => $request->agreed_date?->toDateString(),
            'active_conflict_key' => $request->active_conflict_key,
        ],
        'negotiations' => $request->dateNegotiations()
            ->orderBy('id')
            ->get()
            ->map(fn ($negotiation): array => [
                'status' => $negotiation->status->value,
                'active_key' => $negotiation->active_negotiation_key,
                'proposals' => $negotiation->proposals()->orderBy('id')->get()->map(fn ($proposal): array => [
                    'status' => $proposal->status->value,
                    'responded_by_user_id' => $proposal->responded_by_user_id,
                ])->all(),
            ])->all(),
        'histories' => $request->histories()->orderBy('sequence')->get(['sequence', 'event_type', 'performed_by_user_id'])->map(fn ($history): array => [
            'sequence' => $history->sequence,
            'event_type' => $history->event_type->value,
            'performed_by_user_id' => $history->performed_by_user_id,
        ])->all(),
        'notifications' => PortalNotification::query()
            ->where('request_uuid', $request->uuid)
            ->orderBy('id')
            ->get(['type', 'notifiable_user_id'])
            ->map(fn ($notification): array => ['type' => $notification->type->value, 'user_id' => $notification->notifiable_user_id])
            ->all(),
        'source_events' => SourceProjectionEvent::query()
            ->where('projected_plot_service_id', $service->id)
            ->orderBy('id')
            ->get(['event_type', 'call_off_request_id'])
            ->map(fn ($event): array => ['event_type' => $event->event_type, 'request_id' => $event->call_off_request_id])
            ->all(),
    ];
}
