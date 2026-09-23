<?php

namespace App\Services;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\WaldPilotAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only, bounded presentation queries for the Office overview. */
final class OfficeDashboardQueryService
{
    public function forUser(User $actor): array
    {
        abort_unless($actor->hasCompletePortalProfile() && $actor->isFensterOfficeStaff(), 403);

        $requests = CallOffRequest::query()->whereNull('trashed_at');
        $callOffs = (clone $requests)->whereIn('status', [
            CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster,
        ]);
        $amendments = CallOffDateNegotiation::query()
            ->where('purpose', CallOffNegotiationPurpose::Amendment)
            ->where('status', CallOffNegotiationStatus::Open)
            ->whereHas('callOffRequest', fn (Builder $query) => $query
                ->whereNull('trashed_at')->where('status', CallOffRequestStatus::AmendmentOnHold));
        $officeAmendments = (clone $amendments)->whereDoesntHave('proposals', fn (Builder $query) => $query
            ->where('proposal_type', CallOffDateProposalType::FensterAlternativeDate)
            ->where('status', CallOffDateProposalStatus::AwaitingResponse));

        $importsAvailable = ! $actor->is_preview_user && app(WaldPilotAvailability::class)->enabled();
        $imports = $importsAvailable ? $this->imports() : ['count' => null, 'items' => collect(), 'recent' => collect()];
        $requestCount = (clone $callOffs)->count();
        $amendmentCount = (clone $officeAmendments)->count();

        $upcoming = (clone $requests)
            ->whereIn('status', [CallOffRequestStatus::DateAgreed, CallOffRequestStatus::Approved])
            ->whereDoesntHave('projectedPlotService', fn (Builder $query) => $query
                ->whereNotNull('source_completed_at')->orWhereNotNull('source_completion_observed_at'))
            ->join('call_off_batches as upcoming_batch', 'upcoming_batch.id', '=', 'call_off_requests.call_off_batch_id')
            ->select('call_off_requests.*')
            ->selectRaw("CASE WHEN call_off_requests.status = 'approved' THEN COALESCE(call_off_requests.agreed_date, call_off_requests.requested_date, upcoming_batch.requested_date) ELSE call_off_requests.agreed_date END AS dashboard_date")
            ->whereRaw("CASE WHEN call_off_requests.status = 'approved' THEN COALESCE(call_off_requests.agreed_date, call_off_requests.requested_date, upcoming_batch.requested_date) ELSE call_off_requests.agreed_date END >= ?", [today()->toDateString()])
            ->with(['projectedPlot', 'batch.site'])
            ->orderBy('dashboard_date')->orderBy('call_off_requests.id')->limit(5)->get();

        $recent = CallOffStatusHistory::query()
            ->whereIn('event_type', [
                CallOffHistoryEventType::Submitted, CallOffHistoryEventType::DateRequested,
                CallOffHistoryEventType::DateAgreed, CallOffHistoryEventType::AmendmentRequested,
            ])
            ->whereHas('request')->with(['request.projectedPlot', 'request.batch.site', 'performedBy'])
            ->orderByDesc('performed_at')->orderByDesc('id')->limit(5)->get()
            ->map(fn (CallOffStatusHistory $event): array => [
                'title' => match ($event->event_type) {
                    CallOffHistoryEventType::Submitted, CallOffHistoryEventType::DateRequested => 'Call-off requested',
                    CallOffHistoryEventType::DateAgreed => 'Call-off date agreed',
                    default => 'Date amendment requested',
                },
                'detail' => $this->requestContext($event->request),
                'actor' => $event->recordedActorName(),
                'time' => $event->performed_at,
                'tone' => match ($event->event_type) {
                    CallOffHistoryEventType::DateAgreed => 'green',
                    CallOffHistoryEventType::AmendmentRequested => 'rose',
                    default => 'blue',
                },
                'icon' => match ($event->event_type) {
                    CallOffHistoryEventType::DateAgreed => 'check',
                    CallOffHistoryEventType::AmendmentRequested => 'amendment',
                    default => 'request',
                },
                'url' => route('portal.review-requests.show', $event->request),
            ])->concat($imports['recent'])->sortByDesc(fn (array $item) => $item['time']->getTimestamp())->take(5)->values();

        return [
            'today' => now(),
            'greeting' => match (true) {
                now()->hour < 12 => 'Good morning',
                now()->hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'attentionCount' => $requestCount + $amendmentCount + ($imports['count'] ?? 0),
            'requestCount' => $requestCount,
            'requestItems' => (clone $callOffs)->with(['projectedPlot', 'batch.site'])
                ->latest('created_at')->latest('id')->limit(2)->get(),
            'amendmentCount' => $amendmentCount,
            'amendmentItems' => (clone $officeAmendments)->with(['callOffRequest.projectedPlot', 'callOffRequest.batch.site'])
                ->latest('opened_at')->latest('id')->limit(2)->get(),
            'pendingAmendmentCount' => (clone $amendments)->count(),
            'activeSiteCount' => Site::query()->effectivelyActive()->count(),
            'openRequestCount' => (clone $requests)->whereIn('status', [
                CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster,
                CallOffRequestStatus::AwaitingSiteUser, CallOffRequestStatus::AmendmentOnHold,
            ])->count(),
            'importsAvailable' => $importsAvailable,
            'importCount' => $imports['count'],
            'importItems' => $imports['items'],
            'lastImport' => $imports['recent']->first(),
            'upcoming' => $upcoming,
            'recent' => $recent,
        ];
    }

    private function requestContext(CallOffRequest $request): string
    {
        $reference = $request->projectedPlot->plot_reference;
        $plot = str($reference)->lower()->startsWith('plot ') ? $reference : 'Plot '.$reference;

        return $plot.' · '.$request->batch->site->name.' · '.$request->effectiveServiceIdentifier()?->label();
    }

    private function imports(): array
    {
        $current = DB::table('wald_pilot_uploads as uploads')->where('uploads.state', '!=', 'SUPERSEDED')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('wald_pilot_uploads as newer')
                ->whereColumn('newer.stream_id', 'uploads.stream_id')
                ->whereColumn('newer.export_order', 'uploads.export_order')
                ->whereColumn('newer.revision', '>', 'uploads.revision'));
        $attention = (clone $current)->where(function ($query): void {
            $query->whereIn('uploads.state', ['UPLOADED', 'NEEDS_CLARIFICATION', 'READY', 'FAILED'])
                ->orWhere(function ($query): void {
                    $query->where('uploads.state', 'IN_PROGRESS')
                        ->whereExists(fn ($selection) => $selection->selectRaw('1')->from('wald_pilot_selections as selections')
                            ->whereColumn('selections.pilot_upload_id', 'uploads.id')
                            ->whereNotIn('selections.state', ['COMMITTED', 'SUPERSEDED']));
                });
        });
        $items = (clone $attention)->latest('uploads.updated_at')->latest('uploads.id')->limit(2)
            ->get(['uploads.uuid', 'uploads.state', 'uploads.export_date', 'uploads.export_slot', 'uploads.updated_at'])
            ->map(fn (object $upload): array => [
                'title' => match ($upload->state) {
                    'FAILED' => 'Upload needs attention',
                    'NEEDS_CLARIFICATION' => 'Workbook needs clarification',
                    'READY' => 'Workbook ready to link',
                    'IN_PROGRESS' => 'Site import needs review',
                    default => 'Workbook awaiting review',
                },
                'detail' => Carbon::parse($upload->export_date)->format('j M Y').' · '.strtolower($upload->export_slot).' export',
                'time' => Carbon::parse($upload->updated_at),
                'url' => route('office.workspace.pilot-import.show', $upload->uuid),
            ]);

        // A receipt proves application; an uploaded workbook does not.
        $recent = DB::table('wald_import_receipts as receipts')
            ->join('wald_import_runs as runs', 'runs.id', '=', 'receipts.run_id')
            ->join('sites', 'sites.id', '=', 'runs.site_id')
            ->join('wald_pilot_uploads as uploads', 'uploads.id', '=', 'runs.pilot_upload_id')
            ->latest('receipts.created_at')->latest('receipts.id')->limit(5)
            ->get(['receipts.created_at', 'receipts.actor_name', 'sites.name as site_name', 'uploads.uuid as upload_uuid'])
            ->map(fn (object $receipt): array => [
                'title' => 'Site import applied',
                'detail' => $receipt->site_name,
                'actor' => $receipt->actor_name,
                'time' => Carbon::parse($receipt->created_at),
                'tone' => 'blue',
                'icon' => 'import',
                'url' => route('office.workspace.pilot-import.show', $receipt->upload_uuid),
            ]);

        return ['count' => (clone $attention)->count(), 'items' => $items, 'recent' => $recent];
    }
}
