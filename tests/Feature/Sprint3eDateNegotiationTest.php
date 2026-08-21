<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RejectAlternativeCallOffDateAction;
use App\Actions\CallOff\WithdrawCallOffRequestsAction;
use App\Contracts\HolidayProvider;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('agrees a requested date, preserves the conflict key and records immutable evidence', function (): void {
    [$siteUser, $office, $request] = sprint3eRequest();

    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);

    $request->refresh();
    $negotiation = $request->dateNegotiations()->firstOrFail();
    $proposal = $negotiation->proposals()->firstOrFail();

    expect($request->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($request->agreed_date->toDateString())->toBe($request->requested_date->toDateString())
        ->and($request->active_conflict_key)->not->toBeNull()
        ->and($negotiation->status)->toBe(CallOffNegotiationStatus::DateAgreed)
        ->and($proposal->status)->toBe(CallOffDateProposalStatus::Accepted)
        ->and($request->histories()->where('event_type', CallOffHistoryEventType::DateAgreed)->exists())->toBeTrue()
        ->and(PortalNotification::query()->where('notifiable_user_id', $siteUser->id)->where('type', PortalNotificationType::CallOffDateAgreed)->exists())->toBeTrue();
});

it('requires explicit acknowledgement before agreeing an early requested date', function (): void {
    [, $office, $request] = sprint3eRequest(early: true);

    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $request))
        ->toThrow(ValidationException::class);

    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request->fresh(), true);

    expect($request->fresh()->histories()->where('event_type', CallOffHistoryEventType::EarlierDateExceptionAcknowledged)->exists())->toBeTrue()
        ->and($request->fresh()->dateNegotiations()->firstOrFail()->proposals()->firstOrFail()->earlier_date_acknowledged_at)->not->toBeNull();
});

it('supports repeated alternatives and dates the request only when an alternative is accepted', function (): void {
    [$siteUser, $office, $request] = sprint3eRequest();
    $propose = app(ProposeAlternativeCallOffDateAction::class);
    $reject = app(RejectAlternativeCallOffDateAction::class);

    foreach ([2, 3, 4] as $week) {
        $proposal = $propose->handle($office, $request->fresh(), sprint3eWeekday($week), 'Please consider this date.', 'Internal context.');
        $reject->handle($siteUser, $request->fresh(), $proposal, 'This date does not work.');
    }

    $acceptedProposal = $propose->handle($office, $request->fresh(), sprint3eWeekday(5));
    app(AcceptAlternativeCallOffDateAction::class)->handle($siteUser, $request->fresh(), $acceptedProposal);

    $request->refresh();
    $proposals = $request->dateNegotiations()->firstOrFail()->proposals()->orderBy('sequence')->get();

    expect($request->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($request->agreed_date->toDateString())->toBe($acceptedProposal->proposed_date->toDateString())
        ->and($proposals)->toHaveCount(5)
        ->and($proposals->pluck('sequence')->all())->toBe([1, 2, 3, 4, 5])
        ->and($proposals->where('status', CallOffDateProposalStatus::Rejected))->toHaveCount(3)
        ->and($proposals->where('status', CallOffDateProposalStatus::Superseded))->toHaveCount(1)
        ->and($proposals->where('status', CallOffDateProposalStatus::Accepted))->toHaveCount(1)
        ->and(PortalNotification::query()->where('notifiable_user_id', $siteUser->id)->where('type', PortalNotificationType::CallOffAlternativeProposed)->exists())->toBeTrue()
        ->and(PortalNotification::query()->where('notifiable_user_id', $office->id)->where('type', PortalNotificationType::CallOffAlternativeRejected)->exists())->toBeTrue()
        ->and(PortalNotification::query()->where('notifiable_user_id', $office->id)->where('type', PortalNotificationType::CallOffAlternativeAccepted)->exists())->toBeTrue();
});

it('enforces office and assigned-site authority immediately before date decisions', function (): void {
    [$siteUser, $office, $request, $site] = sprint3eRequest();
    $unassignedUser = sprint3eUser(PortalRoleIdentifier::AssistantSiteManager, $site->customerOrganisation);

    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eWeekday(2));

    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($unassignedUser, $request->fresh(), $proposal))
        ->toThrow(AuthorizationException::class);

    app(AcceptAlternativeCallOffDateAction::class)->handle($siteUser, $request->fresh(), $proposal);

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed);
});

it('rejects stale customer responses once the source service is completed', function (): void {
    [$siteUser, $office, $request] = sprint3eRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eWeekday(2));
    $request->projectedPlotService->update(['source_completed_at' => now()->toDateString()]);

    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($siteUser, $request->fresh(), $proposal))
        ->toThrow(ValidationException::class);

    expect($proposal->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse);
});

it('rejects a second response to the same alternative', function (): void {
    [$siteUser, $office, $request] = sprint3eRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eWeekday(2));
    $accept = app(AcceptAlternativeCallOffDateAction::class);

    $accept->handle($siteUser, $request->fresh(), $proposal);

    expect(fn () => $accept->handle($siteUser, $request->fresh(), $proposal->fresh()))
        ->toThrow(ValidationException::class)
        ->and(CallOffDateProposal::query()->whereKey($proposal->id)->where('status', CallOffDateProposalStatus::Accepted)->count())->toBe(1);
});

it('allows withdrawal while awaiting a date decision and blocks it once a date is agreed', function (): void {
    [$siteUser, $office, $request] = sprint3eRequest();

    app(WithdrawCallOffRequestsAction::class)->handle($siteUser, [$request]);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Withdrawn);

    [$secondSiteUser, $office, $agreedRequest] = sprint3eRequest();
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $agreedRequest);

    expect(fn () => app(WithdrawCallOffRequestsAction::class)->handle($secondSiteUser, [$agreedRequest->fresh()]))
        ->toThrow(ValidationException::class);
});

it('rejects weekend and configured holiday alternative dates server-side', function (): void {
    app()->instance(HolidayProvider::class, new class implements HolidayProvider
    {
        public function isHoliday(CarbonInterface $date): bool
        {
            return $date->toDateString() === '2030-01-07';
        }
    });

    [, $office, $request] = sprint3eRequest();
    $propose = app(ProposeAlternativeCallOffDateAction::class);

    expect(fn () => $propose->handle($office, $request, '2030-01-05'))->toThrow(ValidationException::class)
        ->and(fn () => $propose->handle($office, $request, '2030-01-07'))->toThrow(ValidationException::class);
});

/** @return array{User, User, CallOffRequest, Site} */
function sprint3eRequest(bool $early = false): array
{
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = sprint3eUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = sprint3eUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $siteUser->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $service = ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
        'source_present' => true,
    ]);
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $siteUser->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => sprint3eWeekday(1),
    ]);
    $request = CallOffRequest::query()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => sprint3eWeekday(1),
        'normal_earliest_date' => sprint3eWeekday(2),
        'is_early_date_exception' => $early,
        'early_date_reason' => $early ? 'Customer requested an earlier date.' : null,
        'status' => CallOffRequestStatus::AwaitingFenster,
    ]);

    return [$siteUser, $office, $request, $site];
}

function sprint3eUser(PortalRoleIdentifier $role, CustomerOrganisation $organisation): User
{
    PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);

    return User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
}

function sprint3eWeekday(int $weeks): string
{
    return CarbonImmutable::today()->addWeeks($weeks)->nextWeekday()->toDateString();
}
