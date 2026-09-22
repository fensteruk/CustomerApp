<?php

namespace App\Services;

use App\Models\CustomerOrganisation;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/** The only categories eligible for hard deletion are projections and access assignments. */
final class PermanentDeletionImpact
{
    public function site(Site $site): array
    {
        $site = $site->fresh() ?? $site;
        $id = $site->getKey();
        $plots = DB::table('projected_plots')->where('site_id', $id)->orderBy('id')->pluck('id');
        $services = DB::table('projected_plot_services')->whereIn('projected_plot_id', $plots)->orderBy('id')->pluck('id');
        $products = DB::table('projected_plot_products')->whereIn('projected_plot_id', $plots)->orderBy('id')->pluck('id');
        $assignments = DB::table('site_user_assignments')->where('site_id', $id)->orderBy('id')->pluck('id');
        $counts = [
            'plots' => $plots->count(),
            'plot_services' => $services->count(),
            'plot_products' => $products->count(),
            'site_assignments' => $assignments->count(),
        ];
        $blockers = [
            'customer_requests' => DB::table('call_off_requests')->whereIn('projected_plot_id', $plots)->count(),
            'call_off_batches' => DB::table('call_off_batches')->where('site_id', $id)->count(),
            'source_projection_events' => DB::table('source_projection_events')->whereIn('projected_plot_service_id', $services)->count(),
            'source_projection_issues' => DB::table('source_projection_issues')->whereIn('projected_plot_service_id', $services)->count(),
            'wald_binding_versions' => DB::table('wald_binding_versions')->where('site_id', $id)->count(),
            'wald_import_runs' => DB::table('wald_import_runs')->where('site_id', $id)->count(),
            'wald_pilot_selections' => DB::table('wald_pilot_selections')->where('site_id', $id)->count(),
            'wald_source_rows' => DB::table('wald_source_rows')->where('site_id', $id)->count(),
            'wald_source_visits' => DB::table('wald_source_visits')->where('site_id', $id)->count(),
            'wald_knowledge_contexts' => DB::table('wald_knowledge_contexts')->where('site_id', $id)->count(),
            'wald_profiles' => DB::table('wald_profiles')->where('site_id', $id)->count(),
        ];

        return [
            'uuid' => $site->uuid,
            'name' => $site->name,
            'lock_version' => (int) $site->lock_version,
            'counts' => $counts,
            'blockers' => array_filter($blockers),
            'fingerprint' => $this->fingerprint($site, $counts, $blockers, $plots->all(), $services->all(), $products->all(), $assignments->all()),
        ];
    }

    public function customer(CustomerOrganisation $customer): array
    {
        $customer = $customer->fresh() ?? $customer;
        $sites = Site::query()->where('customer_organisation_id', $customer->getKey())->orderBy('id')->get();
        $siteImpacts = $sites->map(fn (Site $site) => $this->site($site))->all();
        $counts = ['sites' => $sites->count()];
        foreach ($siteImpacts as $impact) {
            foreach ($impact['counts'] as $key => $value) {
                $counts[$key] = ($counts[$key] ?? 0) + $value;
            }
        }
        $blockers = ['customer_users' => DB::table('users')->where('customer_organisation_id', $customer->getKey())->count()];
        foreach ($siteImpacts as $impact) {
            foreach ($impact['blockers'] as $key => $value) {
                $blockers[$key] = ($blockers[$key] ?? 0) + $value;
            }
        }

        return [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'lock_version' => (int) $customer->lock_version,
            'counts' => $counts,
            'blockers' => array_filter($blockers),
            'sites' => $siteImpacts,
            'fingerprint' => hash_hmac('sha256', json_encode([$customer->uuid, (int) $customer->lock_version, $counts, $blockers, array_column($siteImpacts, 'fingerprint')], JSON_THROW_ON_ERROR), config('app.key')),
        ];
    }

    private function fingerprint(Site $site, array $counts, array $blockers, array $plots, array $services, array $products, array $assignments): string
    {
        return hash_hmac('sha256', json_encode([$site->uuid, (int) $site->lock_version, $counts, $blockers, $plots, $services, $products, $assignments], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
