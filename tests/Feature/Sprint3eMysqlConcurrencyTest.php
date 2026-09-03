<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RequestCallOffAmendmentAction;
use App\Data\SourceRecord;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionEvent;
use App\Models\User;
use App\Services\CallOffAmendmentRules;
use App\Services\SourceProjectionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Support\CommittedMysqlFixtureScope;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('This release-gate concurrency suite requires MySQL.');
    }
    CommittedMysqlFixtureScope::assertSafe();
    if (! Schema::hasTable('call_off_requests')) {
        $this->artisan('migrate')->assertExitCode(0);
    }
    app()->instance(CommittedMysqlFixtureScope::class, new CommittedMysqlFixtureScope);
});

afterEach(function (): void {
    if (app()->bound(CommittedMysqlFixtureScope::class)) {
        app(CommittedMysqlFixtureScope::class)->cleanup();
    }
});

test('two Office Staff accept-requested-date attempts produce one truthful transition', function (): void {
    [$siteUser, $office, $secondOffice, $request] = mysqlGateRequestFixture();

    $results = mysqlGateRunWorkers([
        ['agree', $office->id, $request->id],
        ['agree', $secondOffice->id, $request->id],
    ]);

    expect($results->where('ok', true))->toHaveCount(1)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(1)
        ->and(PortalNotification::query()->where('notifiable_user_id', $siteUser->id)->where('type', PortalNotificationType::CallOffDateAgreed)->count())->toBe(1);
});

test('accept-requested-date and propose-alternative-date cannot both commit', function (): void {
    [, $office, $secondOffice, $request] = mysqlGateRequestFixture();

    $results = mysqlGateRunWorkers([
        ['agree', $office->id, $request->id],
        ['propose', $secondOffice->id, $request->id, mysqlGateWeekday(2)],
    ]);

    $fresh = $request->fresh();
    expect($results->where('ok', true))->toHaveCount(1)
        ->and($fresh->status)->toBeIn([CallOffRequestStatus::DateAgreed, CallOffRequestStatus::AwaitingSiteUser])
        ->and($fresh->dateNegotiations()->where('status', CallOffNegotiationStatus::DateAgreed)->count())->toBeLessThanOrEqual(1)
        ->and($fresh->dateNegotiations()->firstOrFail()->proposals()->where('status', CallOffDateProposalStatus::AwaitingResponse)->where('proposal_type', '!=', 'customer_requested_date')->count())->toBeLessThanOrEqual(1);
});

test('two simultaneous alternative proposals leave at most one actionable proposal', function (): void {
    [, $office, $secondOffice, $request] = mysqlGateRequestFixture();

    $results = mysqlGateRunWorkers([
        ['propose', $office->id, $request->id, mysqlGateWeekday(2)],
        ['propose', $secondOffice->id, $request->id, mysqlGateWeekday(3)],
    ]);

    expect($results->where('ok', true))->toHaveCount(1)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingSiteUser)
        ->and($request->fresh()->dateNegotiations()->firstOrFail()->proposals()->where('status', CallOffDateProposalStatus::AwaitingResponse)->where('proposal_type', '!=', 'customer_requested_date')->count())->toBe(1)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::AlternativeDateProposed)->count())->toBe(1);
});

test('two authorised site users cannot both respond to one alternative', function (): void {
    [$siteUser, $office, , $request, $secondSiteUser] = mysqlGateRequestFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));

    $results = mysqlGateRunWorkers([
        ['accept', $siteUser->id, $request->id, $proposal->id],
        ['accept', $secondSiteUser->id, $request->id, $proposal->id],
    ]);

    expect($results->where('ok', true))->toHaveCount(1)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($proposal->fresh()->status)->toBe(CallOffDateProposalStatus::Accepted)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::AlternativeDateAccepted)->count())->toBe(1)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(1);
});

test('accepting and rejecting an alternative cannot create contradictory outcomes', function (): void {
    [$siteUser, $office, , $request, $secondSiteUser] = mysqlGateRequestFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));

    $results = mysqlGateRunWorkers([
        ['accept', $siteUser->id, $request->id, $proposal->id],
        ['reject', $secondSiteUser->id, $request->id, $proposal->id],
    ]);

    $fresh = $request->fresh();
    expect($results->where('ok', true))->toHaveCount(1)
        ->and($proposal->fresh()->status)->toBeIn([CallOffDateProposalStatus::Accepted, CallOffDateProposalStatus::Rejected]);

    if ($proposal->fresh()->status === CallOffDateProposalStatus::Accepted) {
        expect($fresh->status)->toBe(CallOffRequestStatus::DateAgreed)
            ->and($fresh->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(1);
    } else {
        expect($fresh->status)->toBe(CallOffRequestStatus::AwaitingFenster)
            ->and($fresh->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(0);
    }
});

test('source completion supersedes a concurrent customer acceptance without duplicate completion evidence', function (): void {
    [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));

    $results = mysqlGateRunWorkers([
        ['source-complete', $service->id],
        ['accept', $siteUser->id, $request->id, $proposal->id],
    ], [0, 250]);
    $sourceCompletion = $results->where('operation', 'source-complete')->first();

    expect($sourceCompletion['ok'])->toBeTrue(($sourceCompletion['exception'] ?? 'Unknown exception').': '.($sourceCompletion['message'] ?? 'No message.'))
        ->and($service->fresh()->isSourceCompleted())->toBeTrue()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->dateNegotiations()->firstOrFail()->status)->toBe(CallOffNegotiationStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and(SourceProjectionEvent::query()->where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
});

test('completion after a customer acceptance preserves truthful agreement history then supersedes it', function (): void {
    [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));

    $results = mysqlGateRunWorkers([
        ['source-complete', $service->id],
        ['accept', $siteUser->id, $request->id, $proposal->id],
    ], [250, 0]);

    expect($results->where('ok', true))->toHaveCount(2)
        ->and($service->fresh()->isSourceCompleted())->toBeTrue()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(1)
        ->and(SourceProjectionEvent::query()->where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1)
        ->and(PortalNotification::query()->where('type', PortalNotificationType::CallOffDateAgreed)->where('request_uuid', $request->uuid)->count())->toBe(1);
});

test('source completion versus alternative acceptance is deterministic across ten real MySQL races', function (): void {
    foreach (range(1, 10) as $iteration) {
        [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
        $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));
        $completionFirst = $iteration % 2 === 1;
        $results = mysqlGateRunWorkers([
            ['source-complete', $service->id],
            ['accept', $siteUser->id, $request->id, $proposal->id],
        ], $completionFirst ? [0, 250] : [250, 0]);

        $fresh = $request->fresh();
        expect($results->where('operation', 'source-complete')->first()['ok'])->toBeTrue()
            ->and($service->fresh()->isSourceCompleted())->toBeTrue()
            ->and($fresh->status)->toBe(CallOffRequestStatus::Completed)
            ->and($fresh->active_conflict_key)->toBeNull()
            ->and($fresh->dateNegotiations()->where('status', CallOffNegotiationStatus::Open)->count())->toBe(0)
            ->and(SourceProjectionEvent::query()->where('call_off_request_id', $fresh->id)->where('event_type', 'completion_recorded')->count())->toBe(1)
            ->and($fresh->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe($completionFirst ? 0 : 1)
            ->and(PortalNotification::query()->where('type', PortalNotificationType::CallOffDateAgreed)->where('request_uuid', $fresh->uuid)->count())->toBe($completionFirst ? 0 : 1);
    }
});

test('source completion makes every negotiation action stale', function (string $operation): void {
    [$siteUser, $office, , $request, $secondSiteUser, $service] = mysqlGateRequestFixture();
    $proposal = in_array($operation, ['accept', 'reject'], true)
        ? app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2))
        : null;
    $worker = match ($operation) {
        'agree' => ['agree', $office->id, $request->id],
        'propose' => ['propose', $office->id, $request->id, mysqlGateWeekday(3)],
        'accept' => ['accept', $siteUser->id, $request->id, $proposal->id],
        'reject' => ['reject', $secondSiteUser->id, $request->id, $proposal->id],
    };

    $results = mysqlGateRunWorkers([
        ['source-complete', $service->id],
        $worker,
    ], [0, 250]);

    expect($results->where('operation', 'source-complete')->first()['ok'])->toBeTrue()
        ->and($service->fresh()->isSourceCompleted())->toBeTrue()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($request->fresh()->dateNegotiations()->where('status', CallOffNegotiationStatus::Open)->count())->toBe(0)
        ->and(SourceProjectionEvent::query()->where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
})->with(['agree', 'propose', 'accept', 'reject']);

test('a date decision cannot commit after concurrent source availability loss', function (): void {
    [, $office, , $request, , $service] = mysqlGateRequestFixture();

    $results = mysqlGateRunWorkers([
        ['source-missing', $service->id],
        ['agree', $office->id, $request->id],
    ], [0, 250]);

    expect($results->where('operation', 'source-missing')->first()['ok'])->toBeTrue()
        ->and($service->fresh()->source_present)->toBeFalse()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingFenster)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(0)
        ->and(PortalNotification::query()->where('type', PortalNotificationType::CallOffDateAgreed)->where('request_uuid', $request->uuid)->count())->toBe(0);
});

test('accepting an alternative cannot commit after concurrent source availability loss', function (): void {
    [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));

    $results = mysqlGateRunWorkers([
        ['source-missing', $service->id],
        ['accept', $siteUser->id, $request->id, $proposal->id],
    ], [0, 250]);

    expect($results->where('operation', 'source-missing')->first()['ok'])->toBeTrue()
        ->and($service->fresh()->source_present)->toBeFalse()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingSiteUser)
        ->and($proposal->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(0)
        ->and(PortalNotification::query()->where('type', PortalNotificationType::CallOffDateAgreed)->where('request_uuid', $request->uuid)->count())->toBe(0);
});

test('Sprint3F concurrent amendments create exactly one active cycle and notification per Office user', function (): void {
    [$siteUser, $office, , $request, $secondSiteUser] = mysqlGateRequestFixture();
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);
    $request->refresh();
    $revision = app(CallOffAmendmentRules::class)->revision($request);
    $results = mysqlGateRunWorkers([
        ['amend-request', $siteUser->id, $request->id, mysqlGateWeekday(4), $revision],
        ['amend-request', $secondSiteUser->id, $request->id, mysqlGateWeekday(5), $revision],
    ]);
    expect($results->where('ok', true))->toHaveCount(1)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::AmendmentOnHold)
        ->and($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(1)
        ->and($request->histories()->where('event_type', 'amendment_requested')->count())->toBe(1)
        ->and(PortalNotification::where('request_uuid', $request->uuid)->where('notifiable_user_id', $office->id)->where('type', PortalNotificationType::CallOffAmendmentRequested)->count())->toBe(1);
});

test('Sprint3F amendment submission and source completion serialize truthfully in either order', function (bool $completionFirst): void {
    [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);
    $request->refresh();
    $results = mysqlGateRunWorkers([
        ['source-complete', $service->id],
        ['amend-request', $siteUser->id, $request->id, mysqlGateWeekday(4), app(CallOffAmendmentRules::class)->revision($request)],
    ], $completionFirst ? [0, 300] : [300, 0]);
    expect($results->where('operation', 'source-complete')->first()['ok'])->toBeTrue()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($request->dateNegotiations()->whereNotNull('active_negotiation_key')->count())->toBe(0)
        ->and($request->histories()->where('event_type', 'amendment_requested')->count())->toBe($completionFirst ? 0 : 1)
        ->and(SourceProjectionEvent::where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
})->with([true, false]);

test('Sprint3F amendment alternative responses cannot both commit', function (): void {
    [$siteUser, $office, , $request, $secondSiteUser] = mysqlGateRequestFixture();
    $cycle = mysqlGateAmendmentFixture($siteUser, $office, $request);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(5), null, null, $cycle->uuid);
    $results = mysqlGateRunWorkers([
        ['accept', $siteUser->id, $request->id, $proposal->id],
        ['reject', $secondSiteUser->id, $request->id, $proposal->id],
    ]);
    $accepted = $proposal->fresh()->status === CallOffDateProposalStatus::Accepted;
    expect($results->where('ok', true))->toHaveCount(1)
        ->and($request->fresh()->status)->toBe($accepted ? CallOffRequestStatus::DateAgreed : CallOffRequestStatus::AmendmentOnHold)
        ->and($cycle->fresh()->resulting_agreed_date !== null)->toBe($accepted)
        ->and($request->histories()->whereIn('event_type', ['alternative_date_accepted', 'alternative_date_rejected'])->count())->toBe(1);
});

test('Sprint3F source completion wins against amendment decisions in either order without reopening', function (bool $completionFirst, bool $alternative): void {
    [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
    $cycle = mysqlGateAmendmentFixture($siteUser, $office, $request);
    $proposal = $alternative ? app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(5), null, null, $cycle->uuid) : null;
    $results = mysqlGateRunWorkers([
        ['source-complete', $service->id],
        $alternative ? ['accept', $siteUser->id, $request->id, $proposal->id] : ['amend-agree', $office->id, $request->id, $cycle->uuid],
    ], $completionFirst ? [0, 300] : [300, 0]);
    expect($results->where('operation', 'source-complete')->first()['ok'])->toBeTrue()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($request->dateNegotiations()->whereNotNull('active_negotiation_key')->count())->toBe(0)
        ->and($cycle->fresh()->resulting_agreed_date !== null)->toBe(! $completionFirst)
        ->and($request->histories()->where('event_type', 'date_agreed')->count())->toBe($completionFirst ? 1 : 2)
        ->and(PortalNotification::where('request_uuid', $request->uuid)->where('notifiable_user_id', $siteUser->id)->where('type', PortalNotificationType::CallOffDateAgreed)->count())->toBe($completionFirst ? 1 : 2);
})->with([true, false])->with([true, false]);

test('Sprint3F committed worker failure cleanup preserves reference rows and ordinary boundaries', function (): void {
    // This row predates the nested scope and must survive its cleanup byte-for-byte.
    $reference = PortalRole::firstOrCreate(['identifier' => 'site_manager'], ['name' => 'Site Manager']);
    $before = $reference->getAttributes();
    $scope = new CommittedMysqlFixtureScope;
    $barrier = storage_path('app/mysql-gate-'.Str::uuid());
    $process = null;
    try {
        [, $office, , $request] = mysqlGateRequestFixture();
        $process = new Process([PHP_BINARY, base_path('tests/Support/Sprint3eMysqlConcurrencyWorker.php'),
            'crash-after-agree', $office->id, $request->id, 0, $barrier], base_path());
        touch($barrier);
        $process->run();
        expect($process->getExitCode())->toBe(17)
            ->and($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
            ->and($request->histories()->count())->toBe(1);
    } finally {
        if ($process?->isRunning()) {
            $process->stop(2);
        }
        if (file_exists($barrier)) {
            unlink($barrier);
        }
        $scope->cleanup();
    }
    expect($reference->fresh()->getAttributes())->toBe($before);
    foreach (['call_off_requests', 'call_off_date_negotiations', 'call_off_date_proposals', 'call_off_status_histories', 'portal_notifications'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    [, , , $ordinaryRequest] = mysqlGateRequestFixture();
    expect(CallOffRequest::count())->toBe(1)->and($ordinaryRequest->exists)->toBeTrue();
});

test('Sprint3F Office amendment acceptance and alternative proposal have one safe winner', function (): void {
    [$customer, $office, $secondOffice, $request] = mysqlGateRequestFixture();
    $cycle = mysqlGateAmendmentFixture($customer, $office, $request);
    $results = mysqlGateRunWorkers([
        ['amend-agree', $office->id, $request->id, $cycle->uuid],
        ['amend-propose', $secondOffice->id, $request->id, mysqlGateWeekday(6), $cycle->uuid],
    ]);
    expect($results->where('ok', true))->toHaveCount(1)
        ->and($results->where('ok', false)->first()['exception'])->toBe(ValidationException::class);
    $agreed = $request->fresh()->status === CallOffRequestStatus::DateAgreed;
    expect($cycle->fresh()->resulting_agreed_date !== null)->toBe($agreed)
        ->and($cycle->proposals()->where('proposal_type', 'fenster_alternative_date')->count())->toBe($agreed ? 0 : 1)
        ->and($request->histories()->where('event_type', 'date_agreed')->count())->toBe($agreed ? 2 : 1)
        ->and($request->histories()->where('event_type', 'alternative_date_proposed')->count())->toBe($agreed ? 0 : 1);
});

test('Sprint3F completion and proposing or rejecting an amendment serialize in both orders', function (string $operation, bool $completionFirst): void {
    [$customer, $office, , $request, , $service] = mysqlGateRequestFixture();
    $cycle = mysqlGateAmendmentFixture($customer, $office, $request);
    $proposal = $operation === 'reject'
        ? app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(6), null, null, $cycle->uuid)
        : null;
    $worker = $operation === 'reject'
        ? ['reject', $customer->id, $request->id, $proposal->id]
        : ['amend-propose', $office->id, $request->id, mysqlGateWeekday(6), $cycle->uuid];
    $results = mysqlGateRunWorkers([['source-complete', $service->id], $worker], $completionFirst ? [0, 300] : [300, 0]);
    expect($results->where('operation', 'source-complete')->first()['ok'])->toBeTrue()
        ->and($results->where('ok', true)->count())->toBe($completionFirst ? 1 : 2)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($service->fresh()->isSourceCompleted())->toBeTrue()
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Completed)
        ->and($cycle->fresh()->active_negotiation_key)->toBeNull()
        ->and($cycle->fresh()->resulting_agreed_date)->toBeNull()
        ->and($cycle->proposals()->where('status', CallOffDateProposalStatus::AwaitingResponse)->count())->toBe(0)
        ->and($request->histories()->where('event_type', 'date_agreed')->count())->toBe(1)
        ->and(SourceProjectionEvent::where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
    $event = $operation === 'reject' ? 'alternative_date_rejected' : 'alternative_date_proposed';
    expect($request->histories()->where('event_type', $event)->count())->toBe($completionFirst ? 0 : 1);
    if ($proposal !== null) {
        expect($proposal->fresh()->status)->toBe($completionFirst ? CallOffDateProposalStatus::Superseded : CallOffDateProposalStatus::Rejected);
    }
})->with(['propose', 'reject'])->with([true, false]);

test('Sprint3F amendment acceptance versus completion remains stable over ten process races', function (): void {
    foreach (range(1, 10) as $iteration) {
        $scope = new CommittedMysqlFixtureScope;
        try {
            [$customer, $office, , $request, , $service] = mysqlGateRequestFixture();
            $cycle = mysqlGateAmendmentFixture($customer, $office, $request);
            $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(6), null, null, $cycle->uuid);
            $completionFirst = $iteration % 2 === 1;
            $results = mysqlGateRunWorkers([
                ['source-complete', $service->id], ['accept', $customer->id, $request->id, $proposal->id],
            ], $completionFirst ? [0, 300] : [300, 0]);
            expect($results->where('ok', true)->count())->toBe($completionFirst ? 1 : 2)
                ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
                ->and($service->fresh()->isSourceCompleted())->toBeTrue()
                ->and($request->fresh()->active_conflict_key)->toBeNull()
                ->and($cycle->fresh()->active_negotiation_key)->toBeNull()
                ->and($cycle->fresh()->resulting_agreed_date !== null)->toBe(! $completionFirst)
                ->and($proposal->fresh()->status)->toBe($completionFirst ? CallOffDateProposalStatus::Superseded : CallOffDateProposalStatus::Accepted)
                ->and($request->histories()->where('event_type', 'date_agreed')->count())->toBe($completionFirst ? 1 : 2)
                ->and(PortalNotification::where('request_uuid', $request->uuid)->where('notifiable_user_id', $customer->id)->where('type', PortalNotificationType::CallOffDateAgreed)->count())->toBe($completionFirst ? 1 : 2)
                ->and(SourceProjectionEvent::where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
            $notifications = PortalNotification::count();
            $site = $service->projectedPlot->site;
            $importer = app(SourceProjectionImportService::class);
            $record = new SourceRecord($service->source_call_number, $site->external_identifier, $service->projectedPlot->plot_reference, 'PC1', null, CarbonImmutable::today());
            $importer->import($site->external_source, [$record]);
            expect(PortalNotification::count())->toBe($notifications)
                ->and(SourceProjectionEvent::where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
            $importer->import($site->external_source, [new SourceRecord($record->callNumber, $record->siteIdentifier, $record->plotReference, 'PC1', null, null)]);
            expect($service->fresh()->isSourceCompleted())->toBeFalse()
                ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
                ->and($cycle->fresh()->active_negotiation_key)->toBeNull();
            expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal))->toThrow(ValidationException::class);
            expect(PortalNotification::count())->toBe($notifications);
        } finally {
            $scope->cleanup();
        }
    }
});

test('Sprint3F source transaction retry is bounded and rolls back the whole completion attempt', function (string $state, int $driver, int $failures, int $expectedAttempts): void {
    [$customer, $office, , $request, , $service] = mysqlGateRequestFixture();
    $cycle = mysqlGateAmendmentFixture($customer, $office, $request);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(6), null, null, $cycle->uuid);
    $beforeNotifications = PortalNotification::count();
    $beforeHistory = $request->histories()->count();
    $attempts = 0;
    $enabled = true;
    $freshReads = [];
    Event::listen('eloquent.updating: '.ProjectedPlotService::class, function (ProjectedPlotService $updating) use ($service, $request, &$enabled, &$freshReads): void {
        if ($enabled && $updating->id === $service->id) {
            $freshReads[] = $request->fresh()->status === CallOffRequestStatus::AmendmentOnHold
                && ! $service->fresh()->isSourceCompleted()
                && SourceProjectionEvent::where('call_off_request_id', $request->id)->count() === 0;
        }
    });
    // An actual Eloquent write at the END of applyRecord, after closure and source audit writes.
    // Throwing here proves the whole DB transaction rolls back, not just the failing query.
    Event::listen('eloquent.updating: '.ProjectedPlot::class, function (ProjectedPlot $plot) use ($service, $request, &$attempts, &$enabled, $state, $driver, $failures): void {
        if (! $enabled || $plot->id !== $service->projected_plot_id) {
            return;
        }
        $attempts++;
        expect(DB::transactionLevel())->toBe(1)
            ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
            ->and(SourceProjectionEvent::where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
        if ($attempts <= $failures) {
            if ($state === 'validation') {
                throw ValidationException::withMessages(['source' => 'Synthetic business failure']);
            }
            $pdo = new class($state, $driver) extends PDOException
            {
                public function __construct(string $state, int $driver)
                {
                    parent::__construct('Synthetic MySQL gate fault');
                    $this->code = $state;
                    $this->errorInfo = [$state, $driver, 'Synthetic MySQL gate fault'];
                }
            };
            throw new QueryException('mysql', 'synthetic completion end-of-transaction fault', [], $pdo);
        }
    });
    $failure = null;
    try {
        $site = $service->projectedPlot->site;
        $run = app(SourceProjectionImportService::class)->import($site->external_source, [
            // Force the final plot update to be dirty even when fixture/import share a second.
            // Otherwise Eloquent legitimately skips the event where this test injects its fault.
            new SourceRecord($service->source_call_number, $site->external_identifier, $service->projectedPlot->plot_reference, 'PC1', null, CarbonImmutable::today(), [], CarbonImmutable::now()->addMinute()),
        ]);
    } catch (QueryException|ValidationException $exception) {
        $failure = $exception;
    } finally {
        $enabled = false;
    }
    $success = $expectedAttempts > $failures;
    expect($attempts)->toBe($expectedAttempts, $failure?->getMessage() ?? 'Fault injection must execute before evaluating retry behaviour.')
        ->and($freshReads)->toBe(array_fill(0, $expectedAttempts, true))
        ->and($failure === null)->toBe($success)
        ->and($request->fresh()->status)->toBe($success ? CallOffRequestStatus::Completed : CallOffRequestStatus::AmendmentOnHold)
        ->and($service->fresh()->isSourceCompleted())->toBe($success)
        ->and($cycle->fresh()->active_negotiation_key === null)->toBe($success)
        ->and($proposal->fresh()->status)->toBe($success ? CallOffDateProposalStatus::Superseded : CallOffDateProposalStatus::AwaitingResponse)
        ->and($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(1)
        ->and($cycle->proposals()->count())->toBe(2)
        ->and($request->histories()->count())->toBe($beforeHistory)
        ->and(PortalNotification::count())->toBe($beforeNotifications)
        ->and(SourceProjectionEvent::where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe($success ? 1 : 0)
        ->and(SourceImportRun::count())->toBe(1)
        ->and(SourceImportRun::first()->status)->toBe($success ? 'completed' : 'failed');
})->with([
    'serialization two failures then success' => ['40001', 9999, 2, 3],
    'deadlock two failures then success' => ['HY000', 1213, 2, 3],
    'lock timeout two failures then success' => ['HY000', 1205, 2, 3],
    'bounded exhaustion' => ['40001', 1213, 9, 3],
    'non transient integrity failure' => ['23000', 1062, 9, 1],
    'non transient business validation' => ['validation', 0, 9, 1],
]);

function mysqlGateAmendmentFixture(User $siteUser, User $office, CallOffRequest $request): CallOffDateNegotiation
{
    config(['call_off_amendments.reasons' => ['test_reason' => 'Synthetic MySQL test reason']]);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);
    $request->refresh();

    return app(RequestCallOffAmendmentAction::class)->handle($siteUser, $request->batch->site, $request, [
        'requested_date' => mysqlGateWeekday(4), 'reason_code' => 'test_reason',
    ], app(CallOffAmendmentRules::class)->revision($request));
}

/** @return array{User, User, User, CallOffRequest, User, ProjectedPlotService} */
function mysqlGateRequestFixture(): array
{
    $token = (string) Str::uuid();
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = mysqlGateUserFixture(PortalRoleIdentifier::SiteManager, $organisation);
    $secondSiteUser = mysqlGateUserFixture(PortalRoleIdentifier::AssistantSiteManager, $organisation);
    $office = mysqlGateUserFixture(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $secondOffice = mysqlGateUserFixture(PortalRoleIdentifier::FensterOfficeStaff, CustomerOrganisation::factory()->create());
    $site = Site::factory()->create([
        'customer_organisation_id' => $organisation->id,
        'external_source' => 'mysql-gate-'.$token,
        'external_identifier' => 'site-'.$token,
    ]);
    $siteUser->assignedSites()->attach($site);
    $secondSiteUser->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'external_source' => $site->external_source,
        'external_identifier' => 'plot-'.$token,
        'plot_reference' => 'P-'.substr($token, 0, 8),
    ]);
    $service = ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
        'source_call_number' => 'PC-'.$token,
        'source_call_type' => 'PC1',
        'source_present' => true,
    ]);
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $siteUser->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => mysqlGateWeekday(1),
    ]);
    $request = CallOffRequest::query()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => mysqlGateWeekday(1),
        'normal_earliest_date' => mysqlGateWeekday(2),
        'status' => CallOffRequestStatus::AwaitingFenster,
    ]);

    return [$siteUser, $office, $secondOffice, $request, $secondSiteUser, $service];
}

function mysqlGateUserFixture(PortalRoleIdentifier $role, CustomerOrganisation $organisation): User
{
    PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);

    return User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
}

function mysqlGateWeekday(int $weeks): string
{
    return CarbonImmutable::today()->addWeeks($weeks)->nextWeekday()->toDateString();
}

/** @param array<int, array<int, int|string>> $workers
 * @param  array<int, int>  $delaysMilliseconds
 */
function mysqlGateRunWorkers(array $workers, array $delaysMilliseconds = []): Collection
{
    $barrier = storage_path('app/mysql-gate-'.Str::uuid());
    $processes = collect($workers)->map(fn (array $worker, int $index): Process => new Process([
        PHP_BINARY,
        base_path('tests/Support/Sprint3eMysqlConcurrencyWorker.php'),
        ...$worker,
        $delaysMilliseconds[$index] ?? 0,
        $barrier,
    ], base_path()));

    try {
        $processes->each(fn (Process $process) => $process->start());
        usleep(500_000);
        touch($barrier);

        $results = $processes->map(function (Process $process): array {
            $process->wait();

            expect($process->getExitCode())->toBe(0);

            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        });
        if ($directory = getenv('MYSQL_GATE_EVIDENCE_DIR')) {
            file_put_contents($directory.'/race-'.Str::uuid().'.json', json_encode($results->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        foreach ($results->where('ok', false) as $result) {
            expect($result['exception'])->toBe(ValidationException::class, json_encode($results->all()));
        }

        return $results;
    } finally {
        // A timeout/assertion in one child must not leave another child writing during cleanup.
        $processes->each(function (Process $process): void {
            if ($process->isRunning()) {
                $process->stop(2);
            }
        });
        if (file_exists($barrier)) {
            unlink($barrier);
        }
    }
}
