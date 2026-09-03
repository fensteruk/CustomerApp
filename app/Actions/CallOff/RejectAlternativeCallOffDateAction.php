<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAlternativeRejected;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectAlternativeCallOffDateAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly LockCallOffDateNegotiationAggregateAction $locks = new LockCallOffDateNegotiationAggregateAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
        private readonly ResolveCallOffNegotiationAction $cycles = new ResolveCallOffNegotiationAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, CallOffDateProposal $proposal, string $reason): CallOffRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['customer_response' => 'Explain why the alternative date is not suitable.']);
        }
        if (mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['customer_response' => 'The response must not exceed 2000 characters.']);
        }

        $rejectedRequest = DB::transaction(function () use ($actor, $request, $proposal, $reason): CallOffRequest {
            [$request] = $this->locks->handle($request);
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            $this->eligibility->ensureCanRespondToAlternative($actor, $request);
            [$negotiation, $proposal] = $this->cycles->forResponse($request, $proposal);

            $before = $request->stateSnapshot();
            $previous = $request->status;
            $proposal->update(['status' => CallOffDateProposalStatus::Rejected, 'responded_by_user_id' => $actor->id, 'responded_at' => now(), 'customer_response' => $reason]);
            $nextStatus = $negotiation->isAmendment() ? CallOffRequestStatus::AmendmentOnHold : CallOffRequestStatus::AwaitingFenster;
            $request->update(['status' => $nextStatus]);
            $this->history->handle($request, $actor, CallOffHistoryEventType::AlternativeDateRejected, $previous, $nextStatus, $before, $request->fresh()->stateSnapshot() + ['negotiation_uuid' => $negotiation->uuid, 'proposal_uuid' => $proposal->uuid], $reason);

            return $request;
        });

        event(new CallOffAlternativeRejected($rejectedRequest->id, $proposal->uuid));

        return $rejectedRequest;
    }
}
