<?php

namespace App\Services;

use App\Enums\CallOffRequestStatus;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Bounded read-only presentation for the Office Customer Detail page. */
final class CustomerDetailWorkspaceQueryService
{
    public function workspace(User $actor, CustomerOrganisation $customer, array $filters): array
    {
        $queries = app(OfficeAdministrationQueryService::class);
        $record = $queries->customer($actor, $customer->uuid);
        $sites = $queries->sites($actor, $customer, $filters['search'], $filters['active'])->withQueryString();
        $users = app(OfficeUserAdministrationQueryService::class)->customerUsers($actor, $customer, 6);
        $users->getCollection()->loadCount(['assignedSites as active_assigned_sites_count' => fn (Builder $query) => $query
            ->where('sites.customer_organisation_id', $customer->id)->where('sites.is_active', true)]);

        $attention = CallOffRequest::query()
            ->join('call_off_batches as customer_batch', 'customer_batch.id', '=', 'call_off_requests.call_off_batch_id')
            ->whereHas('batch.site', fn (Builder $query) => $query->where('customer_organisation_id', $customer->id))
            ->whereHas('projectedPlot', fn (Builder $query) => $query->whereColumn('projected_plots.site_id', 'customer_batch.site_id'))
            ->whereNull('call_off_requests.trashed_at')
            ->whereDoesntHave('projectedPlotService', fn (Builder $query) => $query
                ->whereNotNull('source_completed_at')->orWhereNotNull('source_completion_observed_at'))
            ->where(function (Builder $query): void {
                $query->whereIn('call_off_requests.status', [CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster])
                    ->orWhere(fn (Builder $amendment) => $amendment
                        ->where('call_off_requests.status', CallOffRequestStatus::AmendmentOnHold)
                        ->whereHas('latestEffectiveAmendment', fn (Builder $cycle) => $cycle->where('status', 'open'))
                        ->whereDoesntHave('latestEffectiveAmendment.proposals', fn (Builder $proposal) => $proposal
                            ->where('proposal_type', 'fenster_alternative_date')->where('status', 'awaiting_response')));
            });
        $siteIds = $customer->sites()->whereIn('uuid', $sites->getCollection()->pluck('uuid'))->pluck('id');
        $siteAttention = (clone $attention)->whereIn('customer_batch.site_id', $siteIds)
            ->selectRaw('customer_batch.site_id, count(*) as total')->groupBy('customer_batch.site_id')->pluck('total', 'site_id');
        $siteKeys = $customer->sites()->whereIn('id', $siteIds)->pluck('id', 'uuid');
        $sites->through(fn (array $site): array => $site + ['attention_count' => $siteAttention->get($siteKeys->get($site['uuid']), 0)]);

        return [
            'customer' => $record,
            'sites' => $sites,
            'customerUsers' => $users,
            'filters' => $filters,
            'plotCount' => ProjectedPlot::query()->whereHas('site', fn (Builder $query) => $query->where('customer_organisation_id', $customer->id))->count(),
            'attentionCount' => (clone $attention)->count(),
            'attentionItems' => (clone $attention)->select('call_off_requests.*')
                ->with(['batch.site', 'projectedPlot', 'latestEffectiveAmendment'])
                ->latest('call_off_requests.updated_at')->latest('call_off_requests.id')->limit(3)->get(),
        ];
    }
}
