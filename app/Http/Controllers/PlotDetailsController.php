<?php

namespace App\Http\Controllers;

use App\Enums\CallOffRequestStatus;
use App\Models\CallOffDateNegotiation;
use App\Models\ProjectedPlot;
use App\Presenters\CustomerProductPresenter;
use App\Services\CallOffDateViewService;
use App\Services\PlotOverviewQueryService;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlotDetailsController extends Controller
{
    public function __invoke(Request $request, ProjectedPlot $projectedPlot, PlotOverviewQueryService $overview, CustomerProductPresenter $products, CallOffDateViewService $dates, CustomerAppDictionary $dictionary): View
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
                ->whereNull('trashed_at')
                ->whereIn('status', array_filter(CallOffRequestStatus::cases(), fn ($status) => $status->isConflictActive()))
                ->with(['batch.site', 'dateNegotiations.proposals', 'histories']),
        ]);

        $requestCards = $projectedPlot->callOffRequests->mapWithKeys(function ($callOffRequest) use ($projectedPlot, $request, $dates) {
            $callOffRequest->setRelation('projectedPlot', $projectedPlot);
            $callOffRequest->setRelation('projectedPlotService', $projectedPlot->services->firstWhere('id', $callOffRequest->projected_plot_service_id));

            return [$callOffRequest->effectiveServiceIdentifier()->value => ['request' => $callOffRequest, 'dates' => $dates->forRequest($callOffRequest, $request->user())]];
        });

        return view('portal.plots.show', [
            'overview' => $overview->present($projectedPlot),
            'activeSite' => $activeSite,
            'products' => $products->present($projectedPlot->products->sortBy('product_code')),
            'totals' => $projectedPlot->products->isEmpty() ? null : $dictionary->rollup($projectedPlot->products->pluck('quantity', 'product_code')->all()),
            'requestCards' => $requestCards,
            'amendments' => CallOffDateNegotiation::query()->where('purpose', 'amendment')
                ->whereHas('callOffRequest', fn ($query) => $query->where('projected_plot_id', $projectedPlot->id)->whereNull('trashed_at'))
                ->with(['callOffRequest.batch', 'proposals'])->orderByDesc('id')->paginate(5, ['*'], 'amendments_page')->withQueryString()->fragment('amendments'),
        ]);
    }
}
