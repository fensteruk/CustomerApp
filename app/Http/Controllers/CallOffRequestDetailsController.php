<?php

namespace App\Http\Controllers;

use App\Models\CallOffRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CallOffRequestDetailsController extends Controller
{
    /**
     * Show the customer-facing history and any current date action for one request.
     */
    public function __invoke(Request $request, CallOffRequest $callOffRequest): View
    {
        $activeSite = $request->attributes->get('activeSite');
        $user = $request->user();

        $callOffRequest->loadMissing('batch.site');

        // The public request UUID is never enough to access a request. It must remain
        // within the currently authorised site context.
        abort_unless(
            $activeSite !== null
            && (int) $callOffRequest->batch->site_id === (int) $activeSite->id
            && $user !== null
            && $user->isSiteRole()
            && $user->canAccessSite($callOffRequest->batch->site),
            404,
        );

        $callOffRequest->load([
            'projectedPlot:id,uuid,site_id,plot_reference',
            'projectedPlotService:id,projected_plot_id,service_identifier,source_present,source_completed_at,source_completion_observed_at',
            'batch:id,site_id,submitted_by_user_id,service_identifier,requested_date,customer_response,submitted_at',
            'batch.site:id,customer_organisation_id,name',
            'batch.submittedBy:id,name,portal_role_id',
            'batch.submittedBy.portalRole:id,identifier,name',
            'histories' => fn ($query) => $query
                ->with('performedBy:id,name,portal_role_id', 'performedBy.portalRole:id,identifier,name')
                ->orderBy('sequence'),
            'dateNegotiations' => fn ($query) => $query
                ->with([
                    'proposals' => fn ($proposalQuery) => $proposalQuery
                        ->with('proposedBy:id,name,portal_role_id', 'proposedBy.portalRole:id,identifier,name', 'respondedBy:id,name,portal_role_id', 'respondedBy.portalRole:id,identifier,name')
                        ->orderBy('sequence'),
                ])
                ->orderBy('opened_at'),
        ]);

        return view('portal.call-offs.show', [
            'activeSite' => $activeSite,
            'callOffRequest' => $callOffRequest,
        ]);
    }
}
