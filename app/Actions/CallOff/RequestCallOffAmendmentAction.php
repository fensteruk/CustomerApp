<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffAmendmentRequested;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffAmendmentRules;
use App\Services\CallOffLeadTimeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestCallOffAmendmentAction
{
    public function __construct(
        private readonly CallOffAmendmentRules $rules,
        private readonly CallOffLeadTimeService $leadTimes,
        private readonly DetermineCallOffEligibilityAction $eligibility,
        private readonly LockCallOffDateNegotiationAggregateAction $locks,
        private readonly RecordCallOffStatusHistoryAction $history,
        private readonly UpdateConflictKeyAction $conflicts,
    ) {}

    public function handle(User $actor, Site $activeSite, CallOffRequest $request, array $input, string $expectedRevision): CallOffDateNegotiation
    {
        $amendment = DB::transaction(function () use ($actor, $activeSite, $request, $input, $expectedRevision): CallOffDateNegotiation {
            [$request, $service] = $this->locks->handle($request);
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            $this->eligibility->ensureCanRequestAmendment($actor, $request);
            if ((int) $request->batch->site_id !== (int) $activeSite->id) {
                throw new AuthorizationException;
            }
            if (! hash_equals($this->rules->revision($request), $expectedRevision)) {
                throw ValidationException::withMessages(['request' => 'This agreed date has changed. Review it again before submitting.']);
            }

            $data = $this->rules->validate($input);
            $date = $this->rules->validateDate($data['requested_date']);
            $priorDate = $request->agreed_date ?? $request->requested_date ?? $request->batch->requested_date;
            if ($priorDate === null || $date->isSameDay($priorDate)) {
                throw ValidationException::withMessages(['requested_date' => 'Choose a different date from the current agreed date.']);
            }
            $earliest = $this->leadTimes->earliestAmendmentDate($service);
            $before = $request->stateSnapshot();
            $amendment = $request->dateNegotiations()->create([
                'purpose' => CallOffNegotiationPurpose::Amendment,
                'status' => CallOffNegotiationStatus::Open,
                'active_negotiation_key' => 'amendment:'.$request->id,
                'prior_agreed_date' => $priorDate,
                'requested_date' => $date,
                'reason_code' => $data['reason_code'],
                'reason_label' => $this->rules->reasons()[$data['reason_code']],
                'customer_response' => $data['customer_response'],
                'requested_by_user_id' => $actor->id,
                'requester_name' => $actor->name,
                'requester_role' => $actor->portalRole->name,
                'is_urgent' => $this->rules->isUrgent($priorDate),
                'is_early_date_exception' => $date->lessThan($earliest),
                'normal_earliest_date' => $earliest,
                'opened_at' => now(),
            ]);
            $amendment->proposals()->create([
                'sequence' => 1,
                'proposal_type' => CallOffDateProposalType::CustomerRequestedDate,
                'status' => CallOffDateProposalStatus::AwaitingResponse,
                'proposed_date' => $date,
                'proposed_by_user_id' => $actor->id,
                'customer_response' => $data['customer_response'],
                'is_earlier_date_exception' => $amendment->is_early_date_exception,
                'proposed_at' => now(),
            ]);
            $previousStatus = $request->status;
            $request->update(['status' => CallOffRequestStatus::AmendmentOnHold]);
            $this->conflicts->handle($request);
            $after = $request->fresh()->stateSnapshot() + [
                'amendment_uuid' => $amendment->uuid,
                'amendment_requested_date' => $date->toDateString(),
                'reason_code' => $amendment->reason_code,
                'reason_label' => $amendment->reason_label,
                'is_urgent' => $amendment->is_urgent,
                'actor_name' => $actor->name,
                'actor_role' => $actor->portalRole->name,
            ];
            $this->history->handle($request, $actor, CallOffHistoryEventType::AmendmentRequested, $previousStatus, CallOffRequestStatus::AmendmentOnHold, $before, $after, $data['customer_response']);

            return $amendment;
        });
        event(new CallOffAmendmentRequested($request->id, $amendment->uuid));

        return $amendment;
    }
}
