<?php

namespace App\Services;

use App\Models\CustomerOrganisation;
use App\Models\Site;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Exact site-owned rows. Shared master uploads, streams and user accounts are retained. */
final class DemoPurgeImpact
{
    public function site(Site $site): array
    {
        $site = Site::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();
        $siteId = $site->getKey();
        $ids = [];
        $ids['site_user_assignments'] = $this->ids('site_user_assignments', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['projected_plots'] = $this->ids('projected_plots', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['projected_plot_services'] = $this->ids('projected_plot_services', fn (Builder $q) => $q->whereIn('projected_plot_id', $ids['projected_plots']));
        $ids['projected_plot_products'] = $this->ids('projected_plot_products', fn (Builder $q) => $q->whereIn('projected_plot_id', $ids['projected_plots']));
        $ids['source_projection_events'] = $this->ids('source_projection_events', fn (Builder $q) => $q->whereIn('projected_plot_service_id', $ids['projected_plot_services']));
        $ids['source_projection_issues'] = $this->ids('source_projection_issues', fn (Builder $q) => $q->whereIn('projected_plot_service_id', $ids['projected_plot_services']));
        $ids['wald_source_rows'] = $this->ids('wald_source_rows', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_source_visits'] = $this->ids('wald_source_visits', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_source_row_observations'] = $this->ids('wald_source_row_observations', fn (Builder $q) => $q->whereIn('source_row_id', $ids['wald_source_rows']));
        $ids['wald_visit_observations'] = $this->ids('wald_visit_observations', fn (Builder $q) => $q->whereIn('visit_id', $ids['wald_source_visits']));
        $ids['wald_binding_versions'] = $this->ids('wald_binding_versions', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_source_bindings'] = DB::table('wald_binding_versions')->whereIn('id', $ids['wald_binding_versions'])->distinct()->orderBy('binding_id')->pluck('binding_id')->all();
        $ids['wald_pilot_selections'] = $this->ids('wald_pilot_selections', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_pilot_events'] = $this->ids('wald_pilot_events', fn (Builder $q) => $q->whereIn('pilot_selection_id', $ids['wald_pilot_selections']));
        $ids['wald_import_runs'] = $this->ids('wald_import_runs', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_import_stages'] = $this->ids('wald_import_stages', fn (Builder $q) => $q->whereIn('run_id', $ids['wald_import_runs']));
        $ids['wald_staged_rows'] = $this->ids('wald_staged_rows', fn (Builder $q) => $q->whereIn('stage_id', $ids['wald_import_stages']));
        $ids['wald_import_previews'] = $this->ids('wald_import_previews', fn (Builder $q) => $q->whereIn('run_id', $ids['wald_import_runs']));
        $ids['wald_import_receipts'] = $this->ids('wald_import_receipts', fn (Builder $q) => $q->whereIn('run_id', $ids['wald_import_runs']));
        $ids['wald_commit_attempts'] = $this->ids('wald_commit_attempts', fn (Builder $q) => $q->whereIn('run_id', $ids['wald_import_runs']));
        $ids['wald_commit_attempt_outcomes'] = $this->ids('wald_commit_attempt_outcomes', fn (Builder $q) => $q->whereIn('attempt_id', $ids['wald_commit_attempts']));
        $ids['wald_knowledge_contexts'] = $this->ids('wald_knowledge_contexts', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_knowledge_evidence'] = $this->ids('wald_knowledge_evidence', fn (Builder $q) => $q->whereIn('context_id', $ids['wald_knowledge_contexts']));
        $ids['wald_clarifications'] = $this->ids('wald_clarifications', fn (Builder $q) => $q->whereIn('context_id', $ids['wald_knowledge_contexts']));
        $ids['wald_clarification_answers'] = $this->ids('wald_clarification_answers', fn (Builder $q) => $q->whereIn('clarification_id', $ids['wald_clarifications']));
        $ids['wald_profiles'] = $this->ids('wald_profiles', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_profile_versions'] = $this->ids('wald_profile_versions', fn (Builder $q) => $q->whereIn('profile_id', $ids['wald_profiles']));
        $ids['wald_knowledge_events'] = $this->ids('wald_knowledge_events', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['wald_profile_uses'] = $this->ids('wald_profile_uses', fn (Builder $q) => $q->whereIn('context_id', $ids['wald_knowledge_contexts']));

        $ids['call_off_batches'] = $this->ids('call_off_batches', fn (Builder $q) => $q->where('site_id', $siteId));
        $ids['call_off_requests'] = $this->ids('call_off_requests', fn (Builder $q) => $q->whereIn('projected_plot_id', $ids['projected_plots']));
        $ids['call_off_date_negotiations'] = $this->ids('call_off_date_negotiations', fn (Builder $q) => $q->whereIn('call_off_request_id', $ids['call_off_requests']));
        $ids['call_off_date_proposals'] = $this->ids('call_off_date_proposals', fn (Builder $q) => $q->whereIn('call_off_date_negotiation_id', $ids['call_off_date_negotiations']));
        $ids['call_off_status_histories'] = $this->ids('call_off_status_histories', fn (Builder $q) => $q->whereIn('call_off_request_id', $ids['call_off_requests']));
        $ids['call_off_batch_operations'] = $this->ids('call_off_batch_operations', fn (Builder $q) => $q->whereIn('call_off_batch_id', $ids['call_off_batches']));
        $ids['call_off_batch_operation_items'] = $this->ids('call_off_batch_operation_items', fn (Builder $q) => $q->whereIn('call_off_request_id', $ids['call_off_requests']));
        $ids['portal_notifications'] = $this->ids('portal_notifications', fn (Builder $q) => $q->where('site_uuid', $site->uuid));

        $blockers = [
            'customer_requests' => count($ids['call_off_requests']),
            'call_off_batches' => count($ids['call_off_batches']),
            'portal_notifications' => count($ids['portal_notifications']),
            'shared_binding_versions' => DB::table('wald_binding_versions')->whereIn('binding_id', $ids['wald_source_bindings'])->where('site_id', '!=', $siteId)->count(),
            'other_site_binding_selections' => DB::table('wald_pilot_selections')->whereIn('binding_id', $ids['wald_source_bindings'])->where('site_id', '!=', $siteId)->count(),
            'other_site_import_successors' => DB::table('wald_import_runs')->whereIn('predecessor_id', $ids['wald_import_runs'])->where('site_id', '!=', $siteId)->count(),
            'other_site_selection_runs' => DB::table('wald_import_runs')->whereIn('pilot_selection_id', $ids['wald_pilot_selections'])->where('site_id', '!=', $siteId)->count(),
            'other_site_preview_receipts' => DB::table('wald_import_receipts')->whereIn('preview_id', $ids['wald_import_previews'])->whereNotIn('run_id', $ids['wald_import_runs'])->count(),
            'other_site_stage_previews' => DB::table('wald_import_previews')->whereIn('stage_id', $ids['wald_import_stages'])->whereNotIn('run_id', $ids['wald_import_runs'])->count(),
            'other_site_receipt_outcomes' => DB::table('wald_commit_attempt_outcomes')->whereIn('receipt_id', $ids['wald_import_receipts'])->whereNotIn('attempt_id', $ids['wald_commit_attempts'])->count(),
            'other_site_source_observations' => DB::table('wald_source_row_observations')->whereIn('source_row_id', $ids['wald_source_rows'])->whereNotIn('run_id', $ids['wald_import_runs'])->count()
                + DB::table('wald_visit_observations')->whereIn('visit_id', $ids['wald_source_visits'])->whereNotIn('run_id', $ids['wald_import_runs'])->count(),
            'other_site_knowledge_runs' => DB::table('wald_import_runs')->whereIn('context_id', $ids['wald_knowledge_contexts'])->where('site_id', '!=', $siteId)->count(),
            'other_site_context_successors' => DB::table('wald_knowledge_contexts')->whereIn('predecessor_id', $ids['wald_knowledge_contexts'])->where('site_id', '!=', $siteId)->count(),
            'other_site_knowledge_answers' => DB::table('wald_clarification_answers')->whereIn('evidence_id', $ids['wald_knowledge_evidence'])->whereNotIn('id', $ids['wald_clarification_answers'])->count(),
            'other_site_answer_successors' => DB::table('wald_clarification_answers')->whereIn('predecessor_id', $ids['wald_clarification_answers'])->whereNotIn('id', $ids['wald_clarification_answers'])->count(),
            'other_site_knowledge_events' => DB::table('wald_knowledge_events')->whereIn('reason_evidence_id', $ids['wald_knowledge_evidence'])->where('site_id', '!=', $siteId)->count(),
            'other_site_profile_uses' => DB::table('wald_profile_uses')->whereIn('profile_id', $ids['wald_profiles'])
                ->whereNotIn('context_id', $ids['wald_knowledge_contexts'])->count(),
            'other_site_visits' => DB::table('wald_source_visits')->where('site_id', '!=', $siteId)->where(function (Builder $q) use ($ids): void {
                $q->whereIn('projected_plot_service_id', $ids['projected_plot_services'])->orWhereIn('source_row_id', $ids['wald_source_rows']);
            })->count(),
            'active_import_operations' => DB::table('wald_import_runs')->whereIn('id', $ids['wald_import_runs'])->where(function (Builder $q): void {
                $q->where('state', 'COMMITTING')->orWhere(function (Builder $lease): void {
                    $lease->whereNotNull('lease_token')->where('lease_until', '>', now('UTC'));
                });
            })->count(),
        ];
        $counts = array_map('count', $ids);
        $retainedUploadIds = array_values(array_unique(array_merge(
            DB::table('wald_pilot_selections')->whereIn('id', $ids['wald_pilot_selections'])->pluck('pilot_upload_id')->all(),
            DB::table('wald_import_runs')->whereIn('id', $ids['wald_import_runs'])->whereNotNull('pilot_upload_id')->pluck('pilot_upload_id')->all(),
        )));
        $state = [
            'site' => [$site->uuid, (int) $site->lock_version],
            'ids' => $ids,
            'blockers' => $blockers,
            'runs' => DB::table('wald_import_runs')->whereIn('id', $ids['wald_import_runs'])->orderBy('id')->lockForUpdate()->get(['id', 'state', 'epoch', 'lease_token', 'lease_until', 'updated_at'])->all(),
            'bindings' => DB::table('wald_source_bindings')->whereIn('id', $ids['wald_source_bindings'])->orderBy('id')->lockForUpdate()->get(['id', 'active_version', 'epoch', 'updated_at'])->all(),
        ];

        return [
            'uuid' => $site->uuid, 'name' => $site->name, 'site_id' => $siteId,
            'counts' => $counts, 'blockers' => array_filter($blockers),
            'retained_uploads' => count($retainedUploadIds), 'retained_upload_ids' => $retainedUploadIds, 'ids' => $ids,
            'fingerprint' => $this->fingerprint($state),
        ];
    }

    public function customer(CustomerOrganisation $customer): array
    {
        $customer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
        $sites = Site::query()->where('customer_organisation_id', $customer->getKey())->orderBy('id')->lockForUpdate()->get();
        $units = $sites->map(fn (Site $site) => $this->site($site))->all();
        $counts = ['sites' => count($units)];
        $blockers = ['customer_users' => DB::table('users')->where('customer_organisation_id', $customer->getKey())->count()];
        foreach ($units as $unit) {
            foreach ($unit['counts'] as $key => $value) {
                $counts[$key] = ($counts[$key] ?? 0) + $value;
            }
            foreach ($unit['blockers'] as $key => $value) {
                $blockers[$key] = ($blockers[$key] ?? 0) + $value;
            }
        }

        return [
            'uuid' => $customer->uuid, 'name' => $customer->name,
            'counts' => $counts, 'blockers' => array_filter($blockers), 'sites' => $units,
            'retained_uploads' => collect($units)->flatMap(fn (array $unit) => $unit['retained_upload_ids'])->unique()->count(),
            'fingerprint' => $this->fingerprint([$customer->uuid, (int) $customer->lock_version, $counts, $blockers, array_column($units, 'fingerprint')]),
        ];
    }

    private function ids(string $table, callable $where): array
    {
        return $where(DB::table($table))->orderBy('id')->lockForUpdate()->pluck('id')->all();
    }

    private function fingerprint(array $state): string
    {
        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), config('app.key'));
    }
}
