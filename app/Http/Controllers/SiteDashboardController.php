<?php

namespace App\Http\Controllers;

use App\Enums\CallOffServiceType;
use App\Enums\PlotOverallStatus;
use App\Enums\PlotServicePresentationState;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Services\SiteWorkspaceQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteDashboardController extends Controller
{
    /**
     * Show the authenticated active assigned-site dashboard.
     */
    public function __invoke(Request $request, SiteWorkspaceQueryService $workspace): View
    {
        $user = $request->user();
        $activeSite = $request->attributes->get('activeSite');
        $filters = $request->validate([
            'plot' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', Rule::enum(CallOffServiceType::class)],
            'status' => ['nullable', 'string', Rule::enum(PlotServicePresentationState::class)],
            'overall_status' => ['nullable', 'array', 'max:5'],
            'overall_status.*' => ['nullable', 'string', Rule::enum(PlotOverallStatus::class)],
            'show_completed' => ['nullable', 'boolean'],
        ]);

        $filters = [
            'plot' => $filters['plot'] ?? '',
            'service' => $filters['service'] ?? '',
            'status' => $filters['status'] ?? '',
            'overall_status' => array_values(array_unique(array_filter($filters['overall_status'] ?? []))),
            'show_completed' => (bool) ($filters['show_completed'] ?? false),
        ];

        $activeFilterCount = (int) filled($filters['plot'])
            + (int) filled($filters['service'])
            + (int) filled($filters['status'])
            + count($filters['overall_status'])
            + (int) $filters['show_completed'];

        return view('portal.site-dashboard', $workspace->workspace($user, $activeSite, $filters) + [
            'activeSite' => $activeSite->load('customerOrganisation'),
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
            'overallStatuses' => PlotOverallStatus::cases(),
            'activeFilterCount' => $activeFilterCount,
            'assignedSites' => Site::query()
                ->assignedTo($user)
                ->orderBy('name')
                ->get(['sites.id', 'sites.name']),
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
