<?php

namespace App\Http\Controllers;

use App\Enums\CallOffServiceType;
use App\Enums\PlotServicePresentationState;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use App\Services\PlotOverviewQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteDashboardController extends Controller
{
    /**
     * Show the authenticated active assigned-site dashboard.
     */
    public function __invoke(Request $request, PlotOverviewQueryService $overview): View
    {
        $user = $request->user();
        $activeSite = $request->attributes->get('activeSite');
        $filters = $request->validate([
            'plot' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', Rule::enum(CallOffServiceType::class)],
            'status' => ['nullable', 'string', Rule::enum(PlotServicePresentationState::class)],
            'show_completed' => ['nullable', 'boolean'],
        ]);

        $filters = [
            'plot' => $filters['plot'] ?? '',
            'service' => $filters['service'] ?? '',
            'status' => $filters['status'] ?? '',
            'show_completed' => (bool) ($filters['show_completed'] ?? false),
        ];

        return view('portal.site-dashboard', [
            'activeSite' => $activeSite->load('customerOrganisation'),
            'plots' => $overview->paginate($activeSite, $filters),
            'legacyRequests' => CallOffRequest::query()
                ->select('call_off_requests.*')
                ->join('call_off_batches', 'call_off_batches.id', '=', 'call_off_requests.call_off_batch_id')
                ->where('call_off_batches.site_id', $activeSite->id)
                ->whereNull('call_off_requests.trashed_at')
                ->with([
                    'projectedPlot:id,site_id,plot_reference,is_completed',
                    'batch:id,site_id,service_identifier,requested_date',
                ])
                ->orderByDesc('call_off_batches.submitted_at')
                ->orderByDesc('call_off_requests.id')
                ->paginate(15, ['*'], 'request_actions_page'),
            'filters' => $filters,
            'serviceTypes' => CallOffServiceType::cases(),
            'states' => PlotServicePresentationState::cases(),
            'lastSynchronisedAt' => ($synchronisedAt = $activeSite->projectedPlots()->max('synchronised_at')) === null
                ? null
                : CarbonImmutable::parse($synchronisedAt),
            'hasProjectedPlots' => $activeSite->projectedPlots()->exists(),
            'missingSourceCount' => ProjectedPlotService::query()
                ->whereHas('projectedPlot', fn ($query) => $query->where('site_id', $activeSite->id))
                ->where('source_present', false)
                ->count(),
            'signedInUser' => $user,
        ]);
    }
}
