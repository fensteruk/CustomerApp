<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffDateAgreed;
use App\Models\CallOffRequest;
use App\Models\User;
use App\Services\CallOffAmendmentRules;
use App\Services\CallOffLeadTimeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgreeRequestedCallOffDateAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly LockCallOffDateNegotiationAggregateAction $locks = new LockCallOffDateNegotiationAggregateAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $history = new RecordCallOffStatusHistoryAction,
        private readonly ResolveCallOffNegotiationAction $cycles = new ResolveCallOffNegotiationAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, bool $acknowledgeEarlyDate = false, ?string $expectedNegotiationUuid = null): CallOffRequest
    {
        [$agreedRequest, $cycleUuid] = DB::transaction(function () use ($actor, $request, $acknowledgeEarlyDate, $expectedNegotiationUuid): array {
            [$request, $service] = $this->locks->handle($request);
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            $this->eligibility->ensureCanAgreeRequestedDate($actor, $request);

            $negotiation = $this->cycles->forOffice($request, $expectedNegotiationUuid);
            $date = $negotiation->isAmendment() ? $negotiation->requested_date : $request->requested_date;
            if ($date === null) {
                throw ValidationException::withMessages(['request' => 'This call-off has no requested date to agree.']);
            }
            $isEarly = $request->is_early_date_exception;
            if ($negotiation->isAmendment()) {
                app(CallOffAmendmentRules::class)->validateDate($date->toDateString());
                $isEarly = $negotiation->is_early_date_exception || $date->lessThan(app(CallOffLeadTimeService::class)->earliestNormalDate($service));
            }

            if ($isEarly && ! $acknowledgeEarlyDate) {
                throw ValidationException::withMessages(['early_date_acknowledgement' => 'Acknowledge the earlier-than-normal date before agreeing it.']);
            }

            $before = $request->stateSnapshot();
            $proposal = $this->cycles->requestedProposal($negotiation, $request);
            $proposal->earlier_date_acknowledged_at = $isEarly ? now() : null;
            $proposal->status = CallOffDateProposalStatus::Accepted;
            $proposal->responded_by_user_id = $actor->id;
            $proposal->responded_at = now();
            $proposal->save();
            $negotiation->update(['status' => CallOffNegotiationStatus::DateAgreed, 'active_negotiation_key' => null, 'closed_at' => now(), ...($negotiation->isAmendment() ? ['resulting_agreed_date' => $date] : [])]);

            $previous = $request->status;
            $request->forceFill(['status' => CallOffRequestStatus::DateAgreed, 'agreed_date' => $date])->save();
            $this->updateConflictKey->handle($request);
            $request->refresh();

            if ($isEarly) {
                $this->history->handle($request, $actor, CallOffHistoryEventType::EarlierDateExceptionAcknowledged, $previous, $previous, $before, $before + [
                    'negotiation_uuid' => $negotiation->uuid,
                    'acknowledged_date' => $date->toDateString(),
                    'acknowledged_normal_earliest_date' => $negotiation->isAmendment()
                        ? app(CallOffLeadTimeService::class)->earliestNormalDate($service)->toDateString()
                        : $request->normal_earliest_date?->toDateString(),
                ]);
            }
            $this->history->handle($request, $actor, CallOffHistoryEventType::DateAgreed, $previous, CallOffRequestStatus::DateAgreed, $before, $request->stateSnapshot() + ['negotiation_uuid' => $negotiation->uuid]);

            return [$request, $negotiation->isAmendment() ? $negotiation->uuid : null];
        });

        event(new CallOffDateAgreed($agreedRequest->id, $cycleUuid));

        return $agreedRequest;
    }
}
