<?php

namespace App\SourceImport\Integration;

use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\SourceImportRun;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectionAdapter
{
    /** Only the guarded whole-run transaction invokes this adapter; no per-record transactions. */
    public function apply(KnowledgeScope $scope, object $run, array $rows, array $snapshot): array
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('projection_transaction_required');
        }

        $sourceRun = SourceImportRun::query()->create([
            'source_name' => $scope->namespace,
            'source_version' => $run->export_order,
            'status' => 'running',
            'started_at' => now(),
            'records_seen' => count($rows),
        ]);
        $changes = collect($snapshot['changes'])->keyBy('call_number');
        $included = array_values(array_filter($rows, fn (array $row): bool => ! $row['excluded']));
        $facts = array_column($included, 'facts');
        $references = array_values(array_unique(array_column($facts, 'plot')));

        $plots = ProjectedPlot::query()->where('site_id', $scope->siteId)->whereIn('plot_reference', $references)->get()->keyBy('plot_reference');
        $newPlots = [];
        foreach ($references as $reference) {
            if (! $plots->has($reference)) {
                $newPlots[] = [
                    'uuid' => (string) Str::uuid(),
                    'site_id' => $scope->siteId,
                    'external_source' => $scope->namespace,
                    'external_identifier' => SourceIdentity::plot($scope->namespace, $scope->siteId, $reference),
                    'plot_reference' => $reference,
                    'synchronised_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($newPlots, 100) as $chunk) {
            DB::table('projected_plots')->insert($chunk);
        }
        if ($newPlots !== []) {
            $plots = ProjectedPlot::query()->where('site_id', $scope->siteId)->whereIn('plot_reference', $references)->get()->keyBy('plot_reference');
        }

        $services = ProjectedPlotService::query()->whereIn('projected_plot_id', $plots->pluck('id'))->get()->keyBy(
            fn (ProjectedPlotService $service): string => $service->projected_plot_id.':'.$service->service_identifier->value,
        );
        $newServices = [];
        foreach ($facts as $fact) {
            if ($fact['service'] === null) {
                continue;
            }
            $key = $plots[$fact['plot']]->id.':'.$fact['service'];
            if (! $services->has($key) && ! isset($newServices[$key])) {
                $newServices[$key] = [
                    'uuid' => (string) Str::uuid(),
                    'projected_plot_id' => $plots[$fact['plot']]->id,
                    'service_identifier' => $fact['service'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk(array_values($newServices), 100) as $chunk) {
            DB::table('projected_plot_services')->insert($chunk);
        }
        if ($newServices !== []) {
            $services = ProjectedPlotService::query()->whereIn('projected_plot_id', $plots->pluck('id'))->get()->keyBy(
                fn (ProjectedPlotService $service): string => $service->projected_plot_id.':'.$service->service_identifier->value,
            );
        }

        $rowHashes = array_map(fn (array $fact): string => SourceIdentity::sourceRow($scope->namespace, $fact['call_number']), $facts);
        $sourceRows = DB::table('wald_source_rows')->whereIn('identity_hash', $rowHashes)->get()->keyBy('identity_hash');
        $visitHashes = array_values(array_filter(array_map(
            fn (array $fact): ?string => $fact['call_type'] === null ? null : SourceIdentity::visit($scope->namespace, $fact['call_number'], $fact['call_type']),
            $facts,
        )));
        $visits = DB::table('wald_source_visits')->whereIn('identity_hash', $visitHashes)->get()->keyBy('identity_hash');

        $completion = new SourceCompletion;
        $transitionIds = $changes
            ->filter(fn (array $change): bool => $change['service'] !== null && $change['before_complete'] !== $change['after_complete'])
            ->map(fn (array $change): int => $services[$plots[$change['plot']]->id.':'.$change['service']]->id)
            ->unique()
            ->values()
            ->all();
        $activeRequests = $completion->prepare($transitionIds);

        $effects = [];
        $products = [];
        $touchedPlots = [];
        foreach ($included as $row) {
            $fact = $row['facts'];
            $diff = $changes[$fact['call_number']];
            $plot = $plots[$fact['plot']];
            $rowKey = SourceIdentity::sourceRow($scope->namespace, $fact['call_number']);
            $sourceRow = $sourceRows->get($rowKey);
            if ($sourceRow && $sourceRow->export_order > $run->export_order) {
                throw new ImportConflict('older_source_row_observation');
            }
            $rowVersion = $sourceRow ? (int) $sourceRow->epoch + 1 : 1;
            $factHash = Canonical::hash($fact);
            $sourceRowId = $sourceRow?->id ?? DB::table('wald_source_rows')->insertGetId([
                'identity_hash' => $rowKey,
                'source_namespace' => $scope->namespace,
                'call_number' => $fact['call_number'],
                'site_id' => $scope->siteId,
                'plot_reference' => $fact['plot'],
                'export_order' => $run->export_order,
                'fact_hash' => $factHash,
                'created_at' => now('UTC'),
            ]);
            $sourceRowObservation = DB::table('wald_source_row_observations')->insertGetId([
                'source_row_id' => $sourceRowId,
                'run_id' => $run->id,
                'version' => $rowVersion,
                'facts' => Canonical::json($fact),
                'provenance' => Canonical::json([
                    'workbook_hash' => $run->workbook_hash,
                    'binding' => $row['binding'],
                    'physical' => $row['provenance'],
                ]),
                'fact_hash' => $factHash,
                'created_at' => now('UTC'),
                'retain_until' => now('UTC')->addYears(6),
            ]);
            DB::table('wald_source_rows')->where('id', $sourceRowId)->update([
                'epoch' => $rowVersion,
                'observation_id' => $sourceRowObservation,
                'export_order' => $run->export_order,
                'fact_hash' => $factHash,
            ]);

            $visitId = null;
            $visitVersion = null;
            $service = null;
            if ($fact['service'] !== null) {
                $service = $services[$plot->id.':'.$fact['service']];
                $wasComplete = $service->isSourceCompleted();
                if ($diff['visit_outcome'] !== 'unchanged') {
                    $service->fill([
                        'source_call_number' => $fact['call_number'],
                        'source_call_type' => $fact['call_type'],
                        'source_job_stage' => null,
                        'source_completed_at' => $diff['after_complete'] ? $service->source_completed_at : null,
                        'source_completion_observed_at' => $diff['after_complete'] ? ($wasComplete ? $service->source_completion_observed_at ?? now() : now()) : null,
                        'last_observed_at' => now(),
                        'source_present' => true,
                        'source_missing_since' => null,
                        'last_source_import_run_id' => $sourceRun->id,
                    ])->save();
                    $completion->transition($service, $sourceRun, $wasComplete, $diff['after_complete'], $activeRequests->get($service->id, collect()));
                }

                $visitKey = SourceIdentity::visit($scope->namespace, $fact['call_number'], $fact['call_type']);
                $visit = $visits->get($visitKey);
                if ($visit === null && $diff['visit_id'] !== null) {
                    $visit = DB::table('wald_source_visits')->where('id', $diff['visit_id'])->first();
                }
                if ($visit && $visit->export_order > $run->export_order) {
                    throw new ImportConflict('older_visit_observation');
                }
                $visitVersion = $visit ? (int) $visit->epoch + 1 : 1;
                $visitId = $visit?->id ?? DB::table('wald_source_visits')->insertGetId([
                    'identity_hash' => $visitKey,
                    'source_namespace' => $scope->namespace,
                    'source_row_id' => $sourceRowId,
                    'call_number' => $fact['call_number'],
                    'call_type' => $fact['call_type'],
                    'site_id' => $scope->siteId,
                    'plot_reference' => $fact['plot'],
                    'service_identifier' => $fact['service'],
                    'projected_plot_service_id' => $service->id,
                    'export_order' => $run->export_order,
                    'fact_hash' => $factHash,
                    'created_at' => now('UTC'),
                ]);
                if ($visit && ($visit->source_row_id === null || $visit->call_type === null)) {
                    DB::table('wald_source_visits')->where('id', $visitId)->update([
                        'source_row_id' => $visit->source_row_id ?? $sourceRowId,
                        'call_type' => $visit->call_type ?? $fact['call_type'],
                    ]);
                }
                $visitObservation = DB::table('wald_visit_observations')->insertGetId([
                    'visit_id' => $visitId,
                    'run_id' => $run->id,
                    'version' => $visitVersion,
                    'facts' => Canonical::json($fact),
                    'provenance' => Canonical::json([
                        'workbook_hash' => $run->workbook_hash,
                        'binding' => $row['binding'],
                        'source_row' => $sourceRowId,
                        'physical' => $row['provenance'],
                    ]),
                    'fact_hash' => $factHash,
                    'created_at' => now('UTC'),
                    'retain_until' => now('UTC')->addYears(6),
                ]);
                DB::table('wald_source_visits')->where('id', $visitId)->update([
                    'epoch' => $visitVersion,
                    'observation_id' => $visitObservation,
                    'export_order' => $run->export_order,
                    'fact_hash' => $factHash,
                ]);
            }

            foreach ($diff['products'] as $code => $quantity) {
                $products[] = [
                    'uuid' => (string) Str::uuid(),
                    'projected_plot_id' => $plot->id,
                    'product_code' => $code,
                    'quantity' => $quantity['after'],
                    'last_source_import_run_id' => $sourceRun->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($diff['outcome'] !== 'unchanged') {
                $touchedPlots[$plot->id] = true;
            }
            $effects[] = [
                'call_number' => $fact['call_number'],
                'source_row' => $sourceRowId,
                'source_row_version' => $rowVersion,
                'visit' => $visitId,
                'visit_version' => $visitVersion,
                'service_id' => $service?->id,
                'outcome' => $diff['outcome'],
            ];
        }

        foreach (array_chunk($products, 100) as $chunk) {
            DB::table('projected_plot_products')->upsert($chunk, ['projected_plot_id', 'product_code'], ['quantity', 'last_source_import_run_id', 'updated_at']);
        }
        if ($touchedPlots !== []) {
            DB::table('projected_plots')->whereIn('id', array_keys($touchedPlots))->update(['synchronised_at' => now(), 'updated_at' => now()]);
        }

        $serviceIds = array_values(array_filter(array_column($effects, 'service_id')));
        $epochs = DB::table('projected_plot_services')->whereIn('id', $serviceIds)->pluck('wald_epoch', 'id');
        foreach ($effects as &$effect) {
            $effect['projection_epoch'] = $effect['service_id'] === null ? null : (int) $epochs[$effect['service_id']];
        }
        unset($effect);

        $created = count(array_filter($effects, fn (array $effect): bool => $effect['outcome'] === 'added'));
        $updated = count(array_filter($effects, fn (array $effect): bool => $effect['outcome'] === 'changed'));
        $unchanged = count(array_filter($effects, fn (array $effect): bool => $effect['outcome'] === 'unchanged'));
        $sourceRun->update([
            'status' => 'completed',
            'finished_at' => now(),
            'records_applied' => count($effects),
            'records_created' => $created,
            'records_updated' => $updated,
            'records_unchanged' => $unchanged,
        ]);

        return [
            'source_run_uuid' => $sourceRun->uuid,
            'counts' => [
                'seen' => count($rows),
                'excluded' => count($rows) - count($included),
                'applied' => count($effects),
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
            ],
            'effects' => $effects,
        ];
    }
}
