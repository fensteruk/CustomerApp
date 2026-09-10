<?php

namespace App\Actions\CallOff;

use App\Contracts\HolidayProvider;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAlternativeProposed;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use App\Services\CallOffAmendmentRules;
use App\Services\CallOffLeadTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposeAlternativeCallOffDateAction
{
    public function __construct(
        private readonly HolidayProvider $holidays,
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly LockCallOffDateNegotiationAggregateAction $locks = new LockCallOffDateNegotiationAggregateAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
        private readonly ResolveCallOffNegotiationAction $cycles = new ResolveCallOffNegotiationAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, string $proposedDate, ?string $customerMessage = null, ?string $internalReason = null, ?string $expectedNegotiationUuid = null, bool $acknowledgeEarlyDate = false): CallOffDateProposal
    {
        $date = $this->validDate($proposedDate);

        $proposal = DB::transaction(function () use ($actor, $request, $date, $customerMessage, $internalReason, $expectedNegotiationUuid, $acknowledgeEarlyDate): CallOffDateProposal {
            [$request, $service] = $this->locks->handle($request);
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            $this->eligibility->ensureCanProposeAlternativeDate($actor, $request);
            $negotiation = $this->cycles->forOffice($request, $expectedNegotiationUuid);
            $requestedDateProposal = $this->cycles->requestedProposal($negotiation, $request);
            $isEarly = false;
            if ($negotiation->isAmendment()) {
                app(CallOffAmendmentRules::class)->validateDate($date->toDateString());
                $isEarly = $date->lessThan(app(CallOffLeadTimeService::class)->earliestNormalDate($service));
                if ($isEarly && ! $acknowledgeEarlyDate) {
                    throw ValidationException::withMessages(['early_date_acknowledgement' => 'Acknowledge the earlier-than-normal date before proposing it.']);
                }
            }

            if ($requestedDateProposal->status === CallOffDateProposalStatus::AwaitingResponse) {
                $requestedDateProposal->update([
                    'status' => CallOffDateProposalStatus::Superseded,
                    'responded_by_user_id' => $actor->id,
                    'responded_at' => now(),
                ]);
            }

            $proposal = $negotiation->proposals()->create(['sequence' => ((int) $negotiation->proposals()->lockForUpdate()->max('sequence')) + 1, 'proposal_type' => CallOffDateProposalType::FensterAlternativeDate, 'status' => CallOffDateProposalStatus::AwaitingResponse, 'proposed_date' => $date, 'proposed_by_user_id' => $actor->id, 'customer_response' => $customerMessage, 'internal_reason' => $internalReason, 'proposed_at' => now(), 'is_earlier_date_exception' => $isEarly, 'earlier_date_acknowledged_at' => $isEarly ? now() : null]);
            $before = $request->stateSnapshot();
            $previous = $request->status;
            $nextStatus = $negotiation->isAmendment() ? CallOffRequestStatus::AmendmentOnHold : CallOffRequestStatus::AwaitingSiteUser;
            $request->update(['status' => $nextStatus]);
            $this->history->handle($request, $actor, CallOffHistoryEventType::AlternativeDateProposed, $previous, $nextStatus, $before, $request->fresh()->stateSnapshot() + ['negotiation_uuid' => $negotiation->uuid, 'proposal_uuid' => $proposal->uuid, 'proposed_date' => $date->toDateString()], $customerMessage, $internalReason);

            return $proposal;
        });

        event(new CallOffAlternativeProposed($request->id, $proposal->uuid));

        return $proposal;
    }

    private function validDate(string $value): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['proposed_date' => 'Enter a valid proposed date.']);
        }

        if (! $date->isWeekday() || $this->holidays->isHoliday($date) || $date->isPast()) {
            throw ValidationException::withMessages(['proposed_date' => 'Alternative dates must be a future Monday to Friday date.']);
        }

        return $date;
    }
}
