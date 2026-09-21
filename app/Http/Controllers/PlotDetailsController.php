<?php

namespace App\Http\Controllers;

use App\Models\ProjectedPlot;
use App\Presenters\CustomerProductPresenter;
use App\Services\PlotOverviewQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlotDetailsController extends Controller
{
    public function __invoke(Request $request, ProjectedPlot $projectedPlot, PlotOverviewQueryService $overview, CustomerProductPresenter $products): View
    {
        $activeSite = $request->attributes->get('activeSite');

        // A public UUID must not let a Site User leave their selected site context, nor
        // reveal whether a plot exists outside that context.
        abort_unless(
            $activeSite !== null
            && (int) $projectedPlot->site_id === (int) $activeSite->id
            && Gate::allows('view-projected-plot', $projectedPlot),
            404,
        );

        $projectedPlot->load([
            'site:id,customer_organisation_id,name',
            'services:id,projected_plot_id,service_identifier,source_completed_at,source_completion_observed_at,source_present,source_missing_since',
            'products:id,projected_plot_id,product_code,quantity',
            'callOffRequests' => fn ($query) => $query
                ->select(['id', 'uuid', 'call_off_batch_id', 'projected_plot_id', 'projected_plot_service_id', 'service_identifier', 'requested_date', 'agreed_date', 'status', 'trashed_at'])
                ->whereNull('trashed_at')
                ->with('batch:id,service_identifier,requested_date'),
        ]);

        return view('portal.plots.show', [
            'overview' => $overview->present($projectedPlot),
            'activeSite' => $activeSite,
            'products' => $products->present($projectedPlot->products->sortBy('product_code')),
        ]);
    }
}
