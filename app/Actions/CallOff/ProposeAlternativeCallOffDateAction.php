<?php

namespace App\Actions\CallOff;

use App\Contracts\HolidayProvider;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAlternativeProposed;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposeAlternativeCallOffDateAction
{
    public function __construct(
        private readonly HolidayProvider $holidays,
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, string $proposedDate, ?string $customerMessage = null, ?string $internalReason = null): CallOffDateProposal
    {
        $date = $this->validDate($proposedDate);

        $proposal = DB::transaction(function () use ($actor, $request, $date, $customerMessage, $internalReason): CallOffDateProposal {
            $request = CallOffRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->ensureCanProposeAlternativeDate($actor, $request);
            $negotiation = CallOffDateNegotiation::query()
                ->where('call_off_request_id', $request->id)
                ->where('purpose', CallOffNegotiationPurpose::Initial)
                ->where('status', CallOffNegotiationStatus::Open)
                ->lockForUpdate()
                ->first();
            $negotiation ??= CallOffDateNegotiation::query()->create(['call_off_request_id' => $request->id, 'purpose' => CallOffNegotiationPurpose::Initial, 'status' => CallOffNegotiationStatus::Open, 'active_negotiation_key' => 'initial:'.$request->id, 'opened_at' => now()]);

            $requestedDateProposal = $this->requestedDateProposal($negotiation, $request);

            if ($negotiation->proposals()
                ->where('status', CallOffDateProposalStatus::AwaitingResponse)
                ->where('proposal_type', '!=', CallOffDateProposalType::CustomerRequestedDate)
                ->exists()) {
                throw ValidationException::withMessages(['proposal' => 'This call-off already has an alternative awaiting a customer response.']);
            }

            if ($requestedDateProposal->status === CallOffDateProposalStatus::AwaitingResponse) {
                $requestedDateProposal->update([
                    'status' => CallOffDateProposalStatus::Superseded,
                    'responded_by_user_id' => $actor->id,
                    'responded_at' => now(),
                ]);
            }

            $proposal = $negotiation->proposals()->create(['sequence' => ((int) $negotiation->proposals()->lockForUpdate()->max('sequence')) + 1, 'proposal_type' => CallOffDateProposalType::FensterAlternativeDate, 'status' => CallOffDateProposalStatus::AwaitingResponse, 'proposed_date' => $date, 'proposed_by_user_id' => $actor->id, 'customer_response' => $customerMessage, 'internal_reason' => $internalReason, 'proposed_at' => now()]);
            $before = $request->stateSnapshot();
            $previous = $request->status;
            $request->update(['status' => CallOffRequestStatus::AwaitingSiteUser]);
            $this->history->handle($request, $actor, CallOffHistoryEventType::AlternativeDateProposed, $previous, CallOffRequestStatus::AwaitingSiteUser, $before, $request->fresh()->stateSnapshot(), $customerMessage, $internalReason);

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

    private function requestedDateProposal(CallOffDateNegotiation $negotiation, CallOffRequest $request): CallOffDateProposal
    {
        $request->loadMissing('batch');

        return CallOffDateProposal::query()->firstOrCreate(
            ['call_off_date_negotiation_id' => $negotiation->id, 'proposal_type' => CallOffDateProposalType::CustomerRequestedDate],
            ['sequence' => 1, 'status' => CallOffDateProposalStatus::AwaitingResponse, 'proposed_date' => $request->requested_date, 'proposed_by_user_id' => $request->batch->submitted_by_user_id, 'customer_response' => $request->customer_response, 'is_earlier_date_exception' => $request->is_early_date_exception, 'proposed_at' => now()],
        );
    }
}
