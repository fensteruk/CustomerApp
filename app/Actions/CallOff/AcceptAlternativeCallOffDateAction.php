<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAlternativeAccepted;
use App\Events\CallOffDateAgreed;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AcceptAlternativeCallOffDateAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly LockCallOffDateNegotiationAggregateAction $locks = new LockCallOffDateNegotiationAggregateAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
        private readonly ResolveCallOffNegotiationAction $cycles = new ResolveCallOffNegotiationAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, CallOffDateProposal $proposal): CallOffRequest
    {
        [$acceptedRequest, $cycleUuid] = DB::transaction(function () use ($actor, $request, $proposal): array {
            [$request] = $this->locks->handle($request);
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            $this->eligibility->ensureCanRespondToAlternative($actor, $request);
            [$negotiation, $proposal] = $this->cycles->forResponse($request, $proposal);

            $before = $request->stateSnapshot();
            $previous = $request->status;
            $proposal->update(['status' => CallOffDateProposalStatus::Accepted, 'responded_by_user_id' => $actor->id, 'responded_at' => now()]);
            $this->history->handle($request, $actor, CallOffHistoryEventType::AlternativeDateAccepted, $previous, $previous, $before, $before + ['negotiation_uuid' => $negotiation->uuid, 'proposal_uuid' => $proposal->uuid]);
            $negotiation->update(['status' => CallOffNegotiationStatus::DateAgreed, 'active_negotiation_key' => null, 'closed_at' => now(), ...($negotiation->isAmendment() ? ['resulting_agreed_date' => $proposal->proposed_date] : [])]);
            $request->update(['status' => CallOffRequestStatus::DateAgreed, 'agreed_date' => $proposal->proposed_date]);
            $this->updateConflictKey->handle($request);
            $request->refresh();
            $this->history->handle($request, $actor, CallOffHistoryEventType::DateAgreed, $previous, CallOffRequestStatus::DateAgreed, $before, $request->stateSnapshot() + ['negotiation_uuid' => $negotiation->uuid]);

            return [$request, $negotiation->isAmendment() ? $negotiation->uuid : null];
        });

        event(new CallOffAlternativeAccepted($acceptedRequest->id, $proposal->uuid));
        event(new CallOffDateAgreed($acceptedRequest->id, $cycleUuid));

        return $acceptedRequest;
    }
}
