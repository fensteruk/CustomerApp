<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAlternativeAccepted;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptAlternativeCallOffDateAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, CallOffDateProposal $proposal): CallOffRequest
    {
        $acceptedRequest = DB::transaction(function () use ($actor, $request, $proposal): CallOffRequest {
            $request = CallOffRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->ensureCanRespondToAlternative($actor, $request);
            $proposal = CallOffDateProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $negotiation = CallOffDateNegotiation::query()->whereKey($proposal->call_off_date_negotiation_id)->lockForUpdate()->firstOrFail();

            if ($negotiation->call_off_request_id !== $request->id || $proposal->status !== CallOffDateProposalStatus::AwaitingResponse) {
                throw ValidationException::withMessages(['proposal' => 'This alternative is no longer available.']);
            }

            $before = $request->stateSnapshot();
            $previous = $request->status;
            $proposal->update(['status' => CallOffDateProposalStatus::Accepted, 'responded_by_user_id' => $actor->id, 'responded_at' => now()]);
            $negotiation->update(['status' => CallOffNegotiationStatus::DateAgreed, 'active_negotiation_key' => null, 'closed_at' => now()]);
            $request->update(['status' => CallOffRequestStatus::DateAgreed, 'agreed_date' => $proposal->proposed_date]);
            $this->updateConflictKey->handle($request);
            $request->refresh();
            $this->history->handle($request, $actor, CallOffHistoryEventType::AlternativeDateAccepted, $previous, CallOffRequestStatus::DateAgreed, $before, $request->stateSnapshot());
            $this->history->handle($request, $actor, CallOffHistoryEventType::DateAgreed, CallOffRequestStatus::DateAgreed, CallOffRequestStatus::DateAgreed, $request->stateSnapshot(), $request->stateSnapshot());

            return $request;
        });

        event(new CallOffAlternativeAccepted($acceptedRequest->id, $proposal->uuid));

        return $acceptedRequest;
    }
}
