<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Semantics\Dictionary\Quantity;
use Illuminate\Support\Facades\DB;

final class ProjectionSnapshot
{
    /** Lock order: services -> requests -> negotiations -> proposals -> history. */
    public function capture(KnowledgeScope $scope, array $rows, object $run): array
    {
        $included = array_values(array_filter($rows, fn (array $row): bool => ! $row['excluded']));
        $facts = array_column($included, 'facts');
        $visitable = array_values(array_filter($facts, fn (array $fact): bool => $fact['call_type'] !== null));
        $references = array_values(array_unique(array_column($facts, 'plot')));
        $rowHashes = array_map(fn (array $fact): string => SourceIdentity::sourceRow($scope->namespace, $fact['call_number']), $facts);
        $visitHashes = array_map(fn (array $fact): string => SourceIdentity::visit($scope->namespace, $fact['call_number'], $fact['call_type']), $visitable);

        $plots = DB::table('projected_plots')->where('site_id', $scope->siteId)->whereIn('plot_reference', $references)->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $target = DB::table('sites')
            ->join('customer_organisations', 'customer_organisations.id', '=', 'sites.customer_organisation_id')
            ->where('sites.id', $scope->siteId)
            ->where('sites.customer_organisation_id', $scope->organisationId)
            ->first([
                'sites.uuid as site_uuid',
                'sites.name as site_name',
                'customer_organisations.uuid as customer_uuid',
                'customer_organisations.name as customer_name',
            ]);
        if ($target === null) {
            throw new ImportConflict('projection_scope_target_missing');
        }
        $services = DB::table('projected_plot_services')->whereIn('projected_plot_id', $plots->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $products = DB::table('projected_plot_products')->whereIn('projected_plot_id', $plots->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $requests = DB::table('call_off_requests')->whereIn('projected_plot_id', $plots->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $negotiations = DB::table('call_off_date_negotiations')->whereIn('call_off_request_id', $requests->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $proposals = DB::table('call_off_date_proposals')->whereIn('call_off_date_negotiation_id', $negotiations->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $history = DB::table('call_off_status_histories')->whereIn('call_off_request_id', $requests->pluck('id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $batches = DB::table('call_off_batches')->whereIn('id', $requests->pluck('call_off_batch_id'))->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $calls = DB::table('projected_plot_services')->whereIn('source_call_number', array_column($visitable, 'call_number'))->orderBy('id')->lockForUpdate()->get();
        $sourceRows = DB::table('wald_source_rows')->where(function ($query) use ($rowHashes, $scope, $facts): void {
            $query->whereIn('identity_hash', $rowHashes)
                ->orWhere(function ($fallback) use ($scope, $facts): void {
                    $fallback->where('source_namespace', $scope->namespace)->whereIn('call_number', array_column($facts, 'call_number'));
                });
        })->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $visits = DB::table('wald_source_visits')->where(function ($query) use ($visitHashes, $sourceRows, $services, $scope, $visitable): void {
            $query->whereIn('identity_hash', $visitHashes)
                ->orWhereIn('source_row_id', $sourceRows->pluck('id'))
                ->orWhereIn('projected_plot_service_id', $services->pluck('id'))
                ->orWhere(function ($legacy) use ($scope, $visitable): void {
                    $legacy->where('source_namespace', $scope->namespace)->whereIn('call_number', array_column($visitable, 'call_number'));
                });
        })->orderBy('id')->limit(10001)->lockForUpdate()->get();
        $rowObservations = DB::table('wald_source_row_observations')->whereIn('id', $sourceRows->pluck('observation_id')->filter())->get()->keyBy('source_row_id');
        $visitObservations = DB::table('wald_visit_observations')->whereIn('id', $visits->pluck('observation_id')->filter())->get()->keyBy('visit_id');

        $digests = [];
        foreach (compact('plots', 'services', 'products', 'requests', 'negotiations', 'proposals', 'history', 'batches', 'calls', 'sourceRows', 'visits') as $key => $items) {
            if ($items->count() > 10000) {
                throw new ImportConflict('projection_history_budget_exceeded');
            }
            $hash = hash_init('sha256');
            foreach ($items as $item) {
                hash_update($hash, Canonical::json((array) $item)."\n");
            }
            $digests[$key] = ['count' => $items->count(), 'hash' => hash_final($hash)];
        }

        $consolidated = (new ProductConsolidator)->consolidate($included);
        if ($consolidated['conflicts'] !== []) {
            throw new ImportConflict('product_quantity_conflict');
        }

        $productChanges = [];
        foreach ($consolidated['products'] as $plotReference => $assertions) {
            $plot = $plots->firstWhere('plot_reference', $plotReference);
            $oldProducts = $plot ? $products->where('projected_plot_id', $plot->id)->pluck('quantity', 'product_code')->all() : [];
            foreach ($assertions as $code => $value) {
                $old = $oldProducts[$code] ?? null;
                if ($old === null || Quantity::units((string) $old) !== Quantity::units($value)) {
                    $productChanges[$plotReference][$code] = ['before' => $old, 'after' => $value];
                }
            }
        }

        $diff = [];
        $claimedProducts = [];
        foreach ($facts as $fact) {
            $matches = $plots->filter(fn (object $plot): bool => $plot->plot_reference === $fact['plot']);
            if ($matches->count() > 1) {
                throw new ImportConflict('ambiguous_existing_plot');
            }
            $plot = $matches->first();
            $rowHash = SourceIdentity::sourceRow($scope->namespace, $fact['call_number']);
            $sourceRow = $sourceRows->first(fn (object $row): bool => $row->identity_hash === $rowHash
                || ($row->source_namespace === $scope->namespace && $row->call_number === $fact['call_number']));
            if ($sourceRow && ((int) $sourceRow->site_id !== $scope->siteId || $sourceRow->plot_reference !== $fact['plot'])) {
                throw new ImportConflict('source_row_identity_changed');
            }
            if ($sourceRow && $sourceRow->export_order > $run->export_order) {
                throw new ImportConflict('older_source_row_observation');
            }
            if ($sourceRow && $sourceRow->export_order === $run->export_order && $sourceRow->fact_hash !== Canonical::hash($fact)
                && ($run->predecessor_id === null || (int) ($rowObservations->get($sourceRow->id)?->run_id) !== (int) $run->predecessor_id)) {
                throw new ImportConflict('explicit_source_row_correction_required');
            }

            $visit = null;
            $service = null;
            $beforeComplete = false;
            $visitOutcome = null;
            $rowVisits = $sourceRow ? $visits->where('source_row_id', $sourceRow->id) : collect();
            if ($fact['call_type'] !== null) {
                if ($rowVisits->contains(fn (object $item): bool => $item->call_type !== null && $item->call_type !== $fact['call_type'])) {
                    throw new ImportConflict('source_row_visit_identity_conflict');
                }
                $visitHash = SourceIdentity::visit($scope->namespace, $fact['call_number'], $fact['call_type']);
                $visit = $visits->first(fn (object $item): bool => $item->identity_hash === $visitHash
                    || ($item->source_namespace === $scope->namespace && $item->call_number === $fact['call_number']
                        && ($item->call_type === null || $item->call_type === $fact['call_type'])));
                $service = $plot ? $services->first(fn (object $item): bool => (int) $item->projected_plot_id === (int) $plot->id && $item->service_identifier === $fact['service']) : null;
                $owners = $service ? $visits->where('projected_plot_service_id', $service->id) : collect();
                if ($owners->count() > 1 || ($owners->isNotEmpty()
                    && ($owners->first()->call_number !== $fact['call_number'] || $owners->first()->source_namespace !== $scope->namespace))) {
                    throw new ImportConflict('projection_source_identity_conflict');
                }
                $call = $calls->first(fn (object $item): bool => $item->source_call_number === $fact['call_number']);
                if ($call && (! $service || (int) $call->id !== (int) $service->id)) {
                    throw new ImportConflict('call_identity_changed');
                }
                if ($service && $service->source_call_number !== null && $service->source_call_number !== $fact['call_number']) {
                    throw new ImportConflict('distinct_visit_projection_requires_review');
                }
                if ($visit && ((int) $visit->site_id !== $scope->siteId || $visit->plot_reference !== $fact['plot']
                    || $visit->service_identifier !== $fact['service'] || ($visit->call_type !== null && $visit->call_type !== $fact['call_type']))) {
                    throw new ImportConflict('call_identity_changed');
                }
                if ($visit && $visit->export_order > $run->export_order) {
                    throw new ImportConflict('older_visit_observation');
                }
                if ($visit && $visit->export_order === $run->export_order && $visit->fact_hash !== Canonical::hash($fact)
                    && ($run->predecessor_id === null || (int) ($visitObservations->get($visit->id)?->run_id) !== (int) $run->predecessor_id)) {
                    throw new ImportConflict('explicit_visit_correction_required');
                }
                $beforeComplete = $service ? ($service->source_completed_at !== null || $service->source_completion_observed_at !== null) : false;
                $visitOutcome = ! $visit ? 'added' : (($visit->fact_hash === Canonical::hash($fact) && $beforeComplete === $fact['complete']) ? 'unchanged' : 'changed');
            }

            $productsForRow = isset($claimedProducts[$fact['plot']]) ? [] : ($productChanges[$fact['plot']] ?? []);
            $claimedProducts[$fact['plot']] = true;
            $rowOutcome = ! $sourceRow ? 'added' : (($sourceRow->fact_hash === Canonical::hash($fact) && $productsForRow === [] && ($visitOutcome === null || $visitOutcome === 'unchanged')) ? 'unchanged' : 'changed');
            $diff[] = [
                'call_number' => $fact['call_number'],
                'plot' => $fact['plot'],
                'service' => $fact['service'],
                'call_type' => $fact['call_type'],
                'plot_id' => $plot?->id,
                'source_row_id' => $sourceRow?->id,
                'source_row_epoch' => $sourceRow ? (int) $sourceRow->epoch : null,
                'visit_id' => $visit?->id,
                'visit_epoch' => $visit ? (int) $visit->epoch : null,
                'service_id' => $service?->id,
                'service_epoch' => $service ? (int) $service->wald_epoch : null,
                'before_complete' => $beforeComplete,
                'after_complete' => $fact['complete'],
                'products' => $productsForRow,
                'visit_outcome' => $visitOutcome,
                'outcome' => $rowOutcome,
            ];
        }

        return [
            'target' => [
                'source' => [
                    'kind' => $rows[0]['facts']['site_kind'],
                    'identity' => $rows[0]['facts']['source_site'],
                    'site_names' => array_values(array_unique(array_values(array_filter(array_map(
                        fn (array $row): ?string => $row['provenance']['source_site_name'] ?? null,
                        $included,
                    ))))),
                ],
                'customer' => ['uuid' => $target->customer_uuid, 'name' => $target->customer_name],
                'site' => ['uuid' => $target->site_uuid, 'name' => $target->site_name],
                'plots' => collect($references)->mapWithKeys(fn (string $reference): array => [
                    $reference => $plots->firstWhere('plot_reference', $reference) === null ? 'CREATE' : 'REUSE',
                ])->all(),
            ],
            'digests' => $digests,
            'changes' => $diff,
            'plot_products' => $consolidated['products'],
            'absence_effect' => 'NONE',
        ];
    }
}
