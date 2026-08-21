<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAlternativeRejected;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectAlternativeCallOffDateAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, CallOffDateProposal $proposal, string $reason): CallOffRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['customer_response' => 'Explain why the alternative date is not suitable.']);
        }

        $rejectedRequest = DB::transaction(function () use ($actor, $request, $proposal, $reason): CallOffRequest {
            $request = CallOffRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->ensureCanRespondToAlternative($actor, $request);
            $proposal = CallOffDateProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $negotiation = CallOffDateNegotiation::query()->whereKey($proposal->call_off_date_negotiation_id)->lockForUpdate()->firstOrFail();

            if ($negotiation->call_off_request_id !== $request->id || $proposal->status !== CallOffDateProposalStatus::AwaitingResponse) {
                throw ValidationException::withMessages(['proposal' => 'This alternative is no longer available.']);
            }

            $before = $request->stateSnapshot();
            $previous = $request->status;
            $proposal->update(['status' => CallOffDateProposalStatus::Rejected, 'responded_by_user_id' => $actor->id, 'responded_at' => now(), 'customer_response' => $reason]);
            $request->update(['status' => CallOffRequestStatus::AwaitingFenster]);
            $this->history->handle($request, $actor, CallOffHistoryEventType::AlternativeDateRejected, $previous, CallOffRequestStatus::AwaitingFenster, $before, $request->fresh()->stateSnapshot(), $reason);

            return $request;
        });

        event(new CallOffAlternativeRejected($rejectedRequest->id, $proposal->uuid));

        return $rejectedRequest;
    }
}
