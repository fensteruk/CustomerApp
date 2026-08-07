<?php

namespace App\Http\Controllers;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteDashboardController extends Controller
{
    /**
     * Show the authenticated active assigned-site dashboard.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('portal.site-dashboard', [
            'activeSite' => $request->attributes->get('activeSite')->load('customerOrganisation'),
            'assignedUsers' => $request->attributes->get('activeSite')->assignedUsers()
                ->orderBy('name')
                ->get(['users.id', 'users.name']),
            'callOffRequests' => CallOffRequest::query()
                ->whereHas('batch', fn ($query) => $query->where('site_id', $request->attributes->get('activeSite')->id))
                ->with([
                    'projectedPlot:id,plot_reference',
                    'batch:id,site_id,submitted_by_user_id,service_identifier,requested_date,customer_response,submitted_at',
                    'batch.submittedBy:id,name',
                    'histories' => fn ($query) => $query
                        ->whereIn('event_type', [
                            CallOffHistoryEventType::Approved->value,
                            CallOffHistoryEventType::Rejected->value,
                        ])
                        ->whereNotNull('customer_response')
                        ->orderByDesc('sequence')
                        ->select('id', 'call_off_request_id', 'event_type', 'sequence', 'customer_response'),
                ])
                ->whereNull('trashed_at')
                ->orderByDesc(
                    CallOffBatch::query()
                        ->select('submitted_at')
                        ->whereColumn('call_off_batches.id', 'call_off_requests.call_off_batch_id')
                        ->limit(1)
                )
                ->latest('id')
                ->get(),
            'outstandingPlots' => $request->attributes->get('activeSite')->projectedPlots()
                ->outstanding()
                ->orderBy('plot_reference')
                ->limit(12)
                ->get(['id', 'plot_reference']),
            'outstandingPlotCount' => $request->attributes->get('activeSite')->projectedPlots()
                ->outstanding()
                ->count(),
            'statusSummaries' => collect(CallOffRequestStatus::cases())->map(fn (CallOffRequestStatus $status): array => [
                'label' => $status->label(),
                'tone' => $status->tone(),
                'count' => CallOffRequest::query()
                    ->where('status', $status->value)
                    ->whereNull('trashed_at')
                    ->whereHas('batch', fn ($query) => $query->where('site_id', $request->attributes->get('activeSite')->id))
                    ->count(),
            ]),
            'signedInUser' => $user,
        ]);
    }
}
