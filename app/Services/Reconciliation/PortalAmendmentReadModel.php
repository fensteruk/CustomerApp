<?php

namespace App\Services\Reconciliation;

use App\Enums\CallOffServiceType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read adapter for the existing amendment records; no new amendment persistence. */
final class PortalAmendmentReadModel
{
    public const VERSION = 'existing-request-amendments.v1';

    public const COMPLETED = "(r.status = 'completed' OR a.status = 'completed' OR ps.source_completed_at IS NOT NULL OR ps.source_completion_observed_at IS NOT NULL)";

    public function forSites(array $siteIds): Builder
    {
        return DB::table('call_off_date_negotiations as a')
            ->join('call_off_requests as r', 'r.id', '=', 'a.call_off_request_id')
            ->join('call_off_batches as b', 'b.id', '=', 'r.call_off_batch_id')
            ->join('projected_plots as p', 'p.id', '=', 'r.projected_plot_id')
            ->join('sites as s', 's.id', '=', 'p.site_id')
            ->join('customer_organisations as c', 'c.id', '=', 's.customer_organisation_id')
            ->leftJoin('projected_plot_services as ps', function ($join): void {
                $join->on('ps.id', '=', 'r.projected_plot_service_id')->on('ps.projected_plot_id', '=', 'p.id')
                    ->whereRaw('ps.service_identifier = COALESCE(r.service_identifier, b.service_identifier)');
            })
            ->whereColumn('b.site_id', 'p.site_id')->whereIn('p.site_id', $siteIds)
            ->where(fn (Builder $q) => $q->whereNull('r.projected_plot_service_id')->orWhereNotNull('ps.id'))
            ->where('a.purpose', 'amendment')->where('a.status', '!=', 'withdrawn')->whereNotNull('a.requested_date')
            ->whereNotIn('r.status', ['withdrawn', 'rejected'])->whereNull('r.trashed_at')
            ->whereIn(DB::raw('COALESCE(r.service_identifier, b.service_identifier)'), array_column(CallOffServiceType::cases(), 'value'))
            ->whereNotExists(function (Builder $q): void {
                $q->selectRaw('1')->from('call_off_date_negotiations as newer')
                    ->whereColumn('newer.call_off_request_id', 'a.call_off_request_id')
                    ->where('newer.purpose', 'amendment')->where('newer.status', '!=', 'withdrawn')
                    ->whereNotNull('newer.requested_date')->whereColumn('newer.id', '>', 'a.id');
            })
            ->select(['a.id as amendment_id', 'a.uuid as amendment_uuid', 'a.call_off_request_id as request_id',
                'a.requested_date as portal_date', 'a.requester_name', 'a.requested_by_user_id', 'a.opened_at',
                'a.status as amendment_status', 'r.uuid as request_uuid', 'r.status as request_status',
                'p.id as plot_id', 'p.uuid as plot_uuid', 'p.plot_reference', 's.id as site_id', 's.uuid as site_uuid',
                's.name as site_name', 'c.name as customer_name'])
            ->selectRaw('COALESCE(r.service_identifier, b.service_identifier) as service')
            ->selectRaw('CASE WHEN '.self::COMPLETED.' THEN 1 ELSE 0 END as completion_closed');
    }
}
