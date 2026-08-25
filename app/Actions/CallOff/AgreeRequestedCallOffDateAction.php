<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffDateAgreed;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgreeRequestedCallOffDateAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly LockCallOffDateNegotiationAggregateAction $locks = new LockCallOffDateNegotiationAggregateAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, bool $acknowledgeEarlyDate = false): CallOffRequest
    {
        $agreedRequest = DB::transaction(function () use ($actor, $request, $acknowledgeEarlyDate): CallOffRequest {
            [$request] = $this->locks->handle($request);
            $this->eligibility->ensureCanAgreeRequestedDate($actor, $request);

            if ($request->requested_date === null) {
                throw ValidationException::withMessages(['request' => 'This call-off has no request-level date to agree.']);
            }

            if ($request->is_early_date_exception && ! $acknowledgeEarlyDate) {
                throw ValidationException::withMessages(['early_date_acknowledgement' => 'Acknowledge the earlier-than-normal date before agreeing it.']);
            }

            $before = $request->stateSnapshot();
            $negotiation = $this->initialNegotiation($request);
            $proposal = $this->requestedDateProposal($negotiation, $request, $acknowledgeEarlyDate);
            $proposal->status = CallOffDateProposalStatus::Accepted;
            $proposal->responded_by_user_id = $actor->id;
            $proposal->responded_at = now();
            $proposal->save();
            $negotiation->update(['status' => CallOffNegotiationStatus::DateAgreed, 'active_negotiation_key' => null, 'closed_at' => now()]);

            $previous = $request->status;
            $request->forceFill(['status' => CallOffRequestStatus::DateAgreed, 'agreed_date' => $request->requested_date])->save();
            $this->updateConflictKey->handle($request);
            $request->refresh();

            if ($request->is_early_date_exception) {
                $this->history->handle($request, $actor, CallOffHistoryEventType::EarlierDateExceptionAcknowledged, $previous, $previous, $before, $before);
            }
            $this->history->handle($request, $actor, CallOffHistoryEventType::DateAgreed, $previous, CallOffRequestStatus::DateAgreed, $before, $request->stateSnapshot());

            return $request;
        });

        event(new CallOffDateAgreed($agreedRequest->id));

        return $agreedRequest;
    }

    private function initialNegotiation(CallOffRequest $request): CallOffDateNegotiation
    {
        return CallOffDateNegotiation::query()
            ->where('call_off_request_id', $request->id)
            ->where('purpose', CallOffNegotiationPurpose::Initial)
            ->lockForUpdate()
            ->first()
            ?? CallOffDateNegotiation::query()->create([
                'call_off_request_id' => $request->id,
                'purpose' => CallOffNegotiationPurpose::Initial,
                'active_negotiation_key' => 'initial:'.$request->id,
                'status' => CallOffNegotiationStatus::Open,
                'opened_at' => now(),
            ]);
    }

    private function requestedDateProposal(CallOffDateNegotiation $negotiation, CallOffRequest $request, bool $acknowledged): CallOffDateProposal
    {
        $request->loadMissing('batch');

        return CallOffDateProposal::query()
            ->where('call_off_date_negotiation_id', $negotiation->id)
            ->where('proposal_type', CallOffDateProposalType::CustomerRequestedDate)
            ->lockForUpdate()
            ->first()
            ?? CallOffDateProposal::query()->create([
                'call_off_date_negotiation_id' => $negotiation->id,
                'proposal_type' => CallOffDateProposalType::CustomerRequestedDate,
                'sequence' => 1,
                'status' => CallOffDateProposalStatus::AwaitingResponse,
                'proposed_date' => $request->requested_date,
                'proposed_by_user_id' => $request->batch->submitted_by_user_id,
                'customer_response' => $request->customer_response,
                'is_earlier_date_exception' => $request->is_early_date_exception,
                'earlier_date_acknowledged_at' => $acknowledged ? now() : null,
                'proposed_at' => now(),
            ]);
    }
}
