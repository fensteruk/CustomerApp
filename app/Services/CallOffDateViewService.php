<?php

namespace App\Services;

use App\Actions\CallOff\DetermineCallOffEligibilityAction;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Models\CallOffRequest;
use App\Models\SourceProjectionEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CallOffDateViewService
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility,
        private readonly CallOffAmendmentRules $rules,
        private readonly CallOffLeadTimeService $leadTimes,
    ) {}

    public function forRequest(CallOffRequest $request, User $actor): array
    {
        $request->loadMissing('dateNegotiations.proposals', 'batch', 'projectedPlotService', 'histories');
        $amendments = $request->dateNegotiations->filter(fn ($cycle) => $cycle->isAmendment())->values();
        $activeAmendment = $amendments->first(fn ($cycle) => $cycle->status->isOpen());
        $isOnHold = $request->status === CallOffRequestStatus::AmendmentOnHold;
        $isCompleted = $request->projectedPlotService?->isSourceCompleted() ?? ($request->status === CallOffRequestStatus::Completed);
        $isClosedAfterReversal = $request->status === CallOffRequestStatus::Completed && ! $isCompleted;
        $isSourceAvailable = $request->projectedPlotService !== null
            && ($request->effectiveServiceIdentifier() === CallOffServiceType::CavityClosers || $request->projectedPlotService->source_present);
        $proposals = $request->dateNegotiations->flatMap(fn ($cycle) => $cycle->proposals)->sortBy([['proposed_at', 'asc'], ['id', 'asc']])->values();
        $currentProposal = $request->dateNegotiations->filter(fn ($cycle) => $cycle->status->isOpen())
            ->flatMap(fn ($cycle) => $cycle->proposals)
            ->first(fn ($proposal) => $proposal->status === CallOffDateProposalStatus::AwaitingResponse && $proposal->proposal_type === CallOffDateProposalType::FensterAlternativeDate);
        $canAmend = false;
        try {
            $this->eligibility->ensureCanRequestAmendment($actor, $request);
            $canAmend = true;
        } catch (AuthorizationException|ValidationException) {
            // Read model: actions still perform their decisive checks under locks.
        }
        $awaitingSiteUser = ! $isCompleted && $isSourceAvailable && $currentProposal !== null
            && ($request->status === CallOffRequestStatus::AwaitingSiteUser || $isOnHold);
        $awaitingFenster = ! $isCompleted && $isSourceAvailable && $currentProposal === null
            && ($request->status === CallOffRequestStatus::AwaitingFenster || ($isOnHold && $activeAmendment !== null));
        $decisionDate = $activeAmendment?->requested_date ?? $request->requested_date;
        $decisionEarliestDate = $activeAmendment !== null && $request->projectedPlotService !== null
            ? $this->leadTimes->earliestAmendmentDate($request->projectedPlotService)
            : $request->normal_earliest_date;
        $requiresEarlyAcknowledgement = $activeAmendment === null ? $request->is_early_date_exception
            : ($activeAmendment->is_early_date_exception
                || ($decisionEarliestDate !== null && $decisionDate !== null && $decisionDate->lessThan($decisionEarliestDate)));
        $cavityCloserEarlyWorkingDays = $activeAmendment === null
            && $request->effectiveServiceIdentifier() === CallOffServiceType::CavityClosers
            && $decisionDate !== null && $decisionEarliestDate !== null && $requiresEarlyAcknowledgement
                ? $this->leadTimes->workingDaysEarly($decisionDate, $decisionEarliestDate) : null;

        return [
            'requestDate' => $request->effectiveRequestedDate(),
            'agreedDate' => ($isOnHold || $isCompleted || $isClosedAfterReversal) ? null : ($request->agreed_date ?? ($request->isLegacyDateAgreed() ? $request->effectiveRequestedDate() : null)),
            'statusLabel' => $isCompleted ? 'Completed' : ($isClosedAfterReversal ? 'Source completion reversed — request remains closed' : ($isOnHold ? ($activeAmendment?->prior_agreed_date ? 'On Hold — Date Change Requested' : ($currentProposal ? 'Awaiting Site User — Amended' : 'Awaiting Fenster — Amended')) : $request->status->label())),
            'isClosedAfterReversal' => $isClosedAfterReversal,
            'isCompleted' => $isCompleted, 'isSourceAvailable' => $isSourceAvailable,
            'isOnHold' => $isOnHold, 'activeAmendment' => $activeAmendment, 'amendments' => $amendments,
            'amendmentEarlyReasons' => $request->histories->filter(fn ($history) => isset($history->after_state['amendment_uuid']))
                ->mapWithKeys(fn ($history) => [$history->after_state['amendment_uuid'] => $history->after_state['early_date_reason'] ?? null]),
            'proposals' => $proposals, 'currentProposal' => $currentProposal,
            'alternativeProposals' => $proposals->filter(fn ($proposal) => $proposal->proposal_type === CallOffDateProposalType::FensterAlternativeDate)->values(),
            'awaitingSiteUser' => $awaitingSiteUser, 'awaitingFenster' => $awaitingFenster,
            'canWithdraw' => ! $isCompleted && in_array($request->status, [CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster, CallOffRequestStatus::AwaitingSiteUser], true),
            'canAmend' => $canAmend,
            'amendmentReasonsReady' => $this->rules->reasons() !== [],
            'decisionDate' => $decisionDate,
            'decisionEarliestDate' => $decisionEarliestDate,
            'requiresEarlyAcknowledgement' => $requiresEarlyAcknowledgement,
            'cavityCloserEarlyWorkingDays' => $cavityCloserEarlyWorkingDays,
            'sourceTimeline' => $request->projected_plot_service_id === null ? collect() : SourceProjectionEvent::query()
                ->where('projected_plot_service_id', $request->projected_plot_service_id)
                ->where(fn ($q) => $q->where('call_off_request_id', $request->id)->orWhereNull('call_off_request_id'))
                ->whereIn('event_type', ['completion_recorded', 'completion_reversed', 'completion_date_updated'])
                ->orderBy('occurred_at')->orderBy('id')->get()
                ->map(fn ($event) => [
                    'label' => match ($event->event_type) {
                        'completion_recorded' => 'Source completion recorded',
                        'completion_reversed' => 'Source completion reversed',
                        default => 'Source completion date updated',
                    },
                    'time' => $event->occurred_at,
                ]),
        ];
    }
}
