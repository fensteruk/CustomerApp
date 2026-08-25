<?php

use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceProjectionEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('This release-gate concurrency suite requires MySQL.');
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

test('source completion wins over a concurrent customer acceptance without duplicate completion evidence', function (): void {
    [$siteUser, $office, , $request, , $service] = mysqlGateRequestFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, mysqlGateWeekday(2));

    $results = mysqlGateRunWorkers([
        ['source-complete', $service->id],
        ['accept', $siteUser->id, $request->id, $proposal->id],
    ]);
    $sourceCompletion = $results->where('operation', 'source-complete')->first();

    expect($sourceCompletion['ok'])->toBeTrue(($sourceCompletion['exception'] ?? 'Unknown exception').': '.($sourceCompletion['message'] ?? 'No message.'))
        ->and($service->fresh()->isSourceCompleted())->toBeTrue()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->dateNegotiations()->firstOrFail()->status)->toBe(CallOffNegotiationStatus::Completed)
        ->and(SourceProjectionEvent::query()->where('call_off_request_id', $request->id)->where('event_type', 'completion_recorded')->count())->toBe(1);
});

test('a date decision cannot commit after concurrent source availability loss', function (): void {
    [, $office, , $request, , $service] = mysqlGateRequestFixture();

    $results = mysqlGateRunWorkers([
        ['source-missing', $service->id],
        ['agree', $office->id, $request->id],
    ]);

    expect($results->where('operation', 'source-missing')->first()['ok'])->toBeTrue()
        ->and($service->fresh()->source_present)->toBeFalse()
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingFenster)
        ->and($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->count())->toBe(0)
        ->and(PortalNotification::query()->where('type', PortalNotificationType::CallOffDateAgreed)->where('request_uuid', $request->uuid)->count())->toBe(0);
});

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

/** @param array<int, array<int, int|string>> $workers */
function mysqlGateRunWorkers(array $workers): Collection
{
    $barrier = storage_path('app/mysql-gate-'.Str::uuid());
    $processes = collect($workers)->map(fn (array $worker): Process => new Process([
        PHP_BINARY,
        base_path('tests/Support/Sprint3eMysqlConcurrencyWorker.php'),
        ...$worker,
        $barrier,
    ], base_path()));

    try {
        $processes->each(fn (Process $process) => $process->start());
        usleep(500_000);
        touch($barrier);

        return $processes->map(function (Process $process): array {
            $process->wait();

            expect($process->getExitCode())->toBe(0);

            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        });
    } finally {
        if (file_exists($barrier)) {
            unlink($barrier);
        }
    }
}
