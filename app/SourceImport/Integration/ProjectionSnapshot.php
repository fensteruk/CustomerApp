<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Semantics\Dictionary\Quantity;
use Illuminate\Support\Facades\DB;

final class ProjectionSnapshot
{
    /** Lock order: services -> requests -> negotiations -> proposals -> history. */
    public function capture(KnowledgeScope $scope, array $rows): array
    {
        $facts = array_column(array_values(array_filter($rows, fn ($r) => ! $r['excluded'])), 'facts');
        $references = array_values(array_unique(array_column($facts, 'plot')));
        $plots = DB::table('projected_plots')->where('site_id', $scope->siteId)->whereIn('plot_reference', $references)->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $services = DB::table('projected_plot_services')->whereIn('projected_plot_id', $plots->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $products = DB::table('projected_plot_products')->whereIn('projected_plot_id', $plots->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $requests = DB::table('call_off_requests')->whereIn('projected_plot_id', $plots->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $negotiations = DB::table('call_off_date_negotiations')->whereIn('call_off_request_id', $requests->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $proposals = DB::table('call_off_date_proposals')->whereIn('call_off_date_negotiation_id', $negotiations->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $history = DB::table('call_off_status_histories')->whereIn('call_off_request_id', $requests->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $batches = DB::table('call_off_batches')->whereIn('id', $requests->pluck('call_off_batch_id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $calls = DB::table('projected_plot_services')->whereIn('source_call_number', array_column($facts, 'call_number'))->orderBy('id')->lockForUpdate()->get();
        $visits = DB::table('wald_source_visits')->whereIn('identity_hash', array_map(fn ($f) => Canonical::hash([$scope->namespace, $f['call_number']]), $facts))->orderBy('id')->lockForUpdate()->get();
        $digests = [];
        foreach (compact('plots', 'services', 'products', 'requests', 'negotiations', 'proposals', 'history', 'batches', 'calls', 'visits') as $key => $items) {
            if ($items->count() > 10000) {
                throw new ImportConflict('projection_history_budget_exceeded');
            }
            $hash = hash_init('sha256');
            foreach ($items as $item) {
                hash_update($hash, Canonical::json((array) $item)."\n");
            }
            $digests[$key] = ['count' => $items->count(), 'hash' => hash_final($hash)];
        }
        $diff = [];
        $plotCalls = [];
        foreach ($facts as $f) {
            $matches = $plots->filter(fn ($p) => $p->plot_reference === $f['plot']);
            if ($matches->count() > 1) {
                throw new ImportConflict('ambiguous_existing_plot');
            }
            $plot = $matches->first();
            $service = $plot ? $services->first(fn ($s) => (int) $s->projected_plot_id === (int) $plot->id && $s->service_identifier === $f['service']) : null;
            $call = $calls->first(fn ($s) => $s->source_call_number === $f['call_number']);
            if ($call && (! $service || (int) $call->id !== (int) $service->id)) {
                throw new ImportConflict('call_identity_changed');
            }
            if ($service && $service->source_call_number !== null && $service->source_call_number !== $f['call_number']) {
                throw new ImportConflict('distinct_visit_projection_requires_review');
            }
            // No unapproved aggregation/latest-visit policy is inferred for conflicting supplied facts.
            if (isset($plotCalls[$f['plot']]) && $plotCalls[$f['plot']] !== $f['call_number']) {
                throw new ImportConflict('competing_plot_visits');
            }
            $plotCalls[$f['plot']] = $f['call_number'];
            $visit = $visits->firstWhere('identity_hash', Canonical::hash([$scope->namespace, $f['call_number']]));
            if ($visit && ((int) $visit->site_id !== $scope->siteId || $visit->plot_reference !== $f['plot'] || $visit->service_identifier !== $f['service'])) {
                throw new ImportConflict('call_identity_changed');
            }
            $oldProducts = $plot ? $products->where('projected_plot_id', $plot->id)->pluck('quantity', 'product_code')->all() : [];
            $changedProducts = [];
            foreach ($f['products'] as $code => $value) {
                $old = $oldProducts[$code] ?? null;
                if ($old === null || Quantity::units((string) $old) !== Quantity::units($value)) {
                    $changedProducts[$code] = ['before' => $old, 'after' => $value];
                }
            }
            $beforeComplete = $service ? ($service->source_completed_at !== null || $service->source_completion_observed_at !== null) : false;
            $diff[] = ['call_number' => $f['call_number'], 'plot' => $f['plot'], 'service' => $f['service'], 'plot_id' => $plot?->id, 'service_id' => $service?->id,
                'service_epoch' => $service ? (int) $service->wald_epoch : null, 'visit_epoch' => $visit ? (int) $visit->epoch : null,
                'before_complete' => $beforeComplete, 'after_complete' => $f['complete'], 'products' => $changedProducts,
                'outcome' => ! $visit ? 'added' : (($visit->fact_hash === Canonical::hash($f) && $changedProducts === [] && $beforeComplete === $f['complete']) ? 'unchanged' : 'changed')];
        }

        return ['digests' => $digests, 'changes' => $diff, 'absence_effect' => 'NONE'];
    }
}
