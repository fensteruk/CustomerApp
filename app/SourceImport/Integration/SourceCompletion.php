<?php

namespace App\SourceImport\Integration;

use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\SourceProjectionIssueType;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionEvent;
use App\Services\SourceProjectionIssueService;
use Illuminate\Support\Collection;

/** Current Portal completion precedence, not the obsolete source mapper/importer. */
final class SourceCompletion
{
    /** ProjectionSnapshot has already acquired the aggregate locks; load relations once per run. */
    public function prepare(array $serviceIds): Collection
    {
        if ($serviceIds === []) {
            return collect();
        }

        return CallOffRequest::query()->whereIn('projected_plot_service_id', $serviceIds)
            ->whereIn('status', [CallOffRequestStatus::Submitted, CallOffRequestStatus::Approved,
                CallOffRequestStatus::AwaitingFenster, CallOffRequestStatus::AwaitingSiteUser, CallOffRequestStatus::DateAgreed, CallOffRequestStatus::AmendmentOnHold])
            ->with(['batch', 'projectedPlotService', 'dateNegotiations' => fn ($q) => $q->where('status', CallOffNegotiationStatus::Open)->orderBy('id'),
                'dateNegotiations.proposals' => fn ($q) => $q->where('status', CallOffDateProposalStatus::AwaitingResponse)->orderBy('id')])
            ->orderBy('id')->get()->groupBy('projected_plot_service_id');
    }

    public function transition(ProjectedPlotService $service, SourceImportRun $run, bool $wasComplete, bool $complete, Collection $requests): void
    {
        if ($wasComplete === $complete) {
            return;
        }
        if (! $complete) {
            if ($requests->isNotEmpty()) {
                (new SourceProjectionIssueService)->record($run, SourceProjectionIssueType::UnsafeCompletionReversal,
                    'unsafe-completion-reversal:'.$service->source_call_number, $service->source_call_number, $service, ['active_request_uuid' => $requests->first()->uuid]);
            }
            $this->event($service, $run, 'completion_reversed', null, ['source_complete' => true], ['source_complete' => false]);

            return;
        }
        foreach ($requests as $request) {
            $negotiations = $request->dateNegotiations;
            foreach ($negotiations as $negotiation) {
                $negotiation->proposals
                    ->each(fn ($p) => $p->update(['status' => CallOffDateProposalStatus::Superseded]));
                $negotiation->update(['status' => CallOffNegotiationStatus::Completed, 'active_negotiation_key' => null, 'closed_at' => now()]);
            }
            $old = $request->status->value;
            $request->status = CallOffRequestStatus::Completed;
            (new UpdateConflictKeyAction)->handle($request);
            $this->event($service, $run, 'completion_recorded', $request->id, ['request_status' => $old], ['request_status' => CallOffRequestStatus::Completed->value]);
        }
        if ($requests->isEmpty()) {
            $this->event($service, $run, 'completion_recorded', null, ['source_complete' => false], ['source_complete' => true]);
        }
    }

    private function event($service, $run, string $type, ?int $request, array $before, array $after): void
    {
        SourceProjectionEvent::query()->create(['source_import_run_id' => $run->id, 'projected_plot_service_id' => $service->id,
            'call_off_request_id' => $request, 'event_type' => $type, 'before_state' => $before, 'after_state' => $after, 'occurred_at' => now()]);
    }
}
