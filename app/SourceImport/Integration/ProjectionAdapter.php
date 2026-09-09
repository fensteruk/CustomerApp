<?php

namespace App\SourceImport\Integration;

use App\Enums\CallOffServiceType;
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
        $sourceRun = SourceImportRun::query()->create(['source_name' => $scope->namespace, 'source_version' => $run->export_order,
            'status' => 'running', 'started_at' => now(), 'records_seen' => count($rows)]);
        $changes = collect($snapshot['changes'])->keyBy('call_number');
        $effects = [];
        $products = [];
        $included = array_values(array_filter($rows, fn ($r) => ! $r['excluded']));
        $plots = ProjectedPlot::query()->where('site_id', $scope->siteId)->whereIn('plot_reference', array_column(array_column($included, 'facts'), 'plot'))->get()->keyBy('plot_reference');
        $newPlots = [];
        foreach ($included as $row) {
            $f = $row['facts'];
            if (! $plots->has($f['plot'])) {
                $newPlots[] = ['uuid' => (string) Str::uuid(), 'site_id' => $scope->siteId, 'external_source' => $scope->namespace,
                    'external_identifier' => 'wald:'.Canonical::hash([$scope->siteId, $f['plot']]), 'plot_reference' => $f['plot'], 'synchronised_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($newPlots, 100) as $chunk) {
            DB::table('projected_plots')->insert($chunk);
        }
        if ($newPlots !== []) {
            $plots = ProjectedPlot::query()->where('site_id', $scope->siteId)->whereIn('plot_reference', array_column(array_column($included, 'facts'), 'plot'))->get()->keyBy('plot_reference');
        }
        $services = ProjectedPlotService::query()->whereIn('projected_plot_id', $plots->pluck('id'))->get()->keyBy(fn ($s) => $s->projected_plot_id.':'.$s->service_identifier->value);
        $newServices = [];
        foreach ($plots as $plot) {
            foreach (CallOffServiceType::cases() as $type) {
                if (! $services->has($plot->id.':'.$type->value)) {
                    $newServices[] = ['uuid' => (string) Str::uuid(), 'projected_plot_id' => $plot->id,
                        'service_identifier' => $type->value, 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }
        foreach (array_chunk($newServices, 100) as $chunk) {
            DB::table('projected_plot_services')->insert($chunk);
        }
        if ($newServices !== []) {
            $services = ProjectedPlotService::query()->whereIn('projected_plot_id', $plots->pluck('id'))->get()->keyBy(fn ($s) => $s->projected_plot_id.':'.$s->service_identifier->value);
        }
        $visits = DB::table('wald_source_visits')->whereIn('identity_hash', array_map(fn ($r) => Canonical::hash([$scope->namespace, $r['facts']['call_number']]), $included))->get()->keyBy('identity_hash');
        $completion = new SourceCompletion;
        $transitionIds = $changes->filter(fn ($change) => $change['before_complete'] !== $change['after_complete'])
            ->map(fn ($change) => $services[$plots[$change['plot']]->id.':'.$change['service']]->id)->all();
        $activeRequests = $completion->prepare($transitionIds);
        foreach ($rows as $row) {
            if ($row['excluded']) {
                continue;
            }
            $f = $row['facts'];
            $diff = $changes[$f['call_number']];
            $plot = $plots[$f['plot']];
            $service = $services[$plot->id.':'.$f['service']];
            $wasComplete = $service->isSourceCompleted();
            if ($diff['outcome'] !== 'unchanged') {
                $service->fill(['source_call_number' => $f['call_number'], 'source_call_type' => $f['call_type'], 'source_job_stage' => null,
                    'source_completed_at' => $f['complete'] ? $service->source_completed_at : null, 'source_completion_observed_at' => $f['complete'] ? ($wasComplete ? $service->source_completion_observed_at ?? now() : now()) : null,
                    'last_observed_at' => now(), 'source_present' => true, 'source_missing_since' => null, 'last_source_import_run_id' => $sourceRun->id])->save();
                $completion->transition($service, $sourceRun, $wasComplete, $f['complete'], $activeRequests->get($service->id, collect()));
                foreach ($diff['products'] as $code => $quantity) {
                    $products[] = ['uuid' => (string) Str::uuid(), 'projected_plot_id' => $plot->id, 'product_code' => $code,
                        'quantity' => $quantity['after'], 'last_source_import_run_id' => $sourceRun->id, 'created_at' => now(), 'updated_at' => now()];
                }
                $plot->update(['synchronised_at' => now()]);
            }
            $key = Canonical::hash([$scope->namespace, $f['call_number']]);
            $visit = $visits->get($key);
            if ($visit && $visit->export_order > $run->export_order) {
                throw new ImportConflict('older_visit_observation');
            }
            $version = $visit ? (int) $visit->epoch + 1 : 1;
            $factHash = Canonical::hash($f);
            $visitId = $visit?->id ?? DB::table('wald_source_visits')->insertGetId(['identity_hash' => $key, 'source_namespace' => $scope->namespace,
                'call_number' => $f['call_number'], 'site_id' => $scope->siteId, 'plot_reference' => $f['plot'], 'service_identifier' => $f['service'],
                'projected_plot_service_id' => $service->id, 'export_order' => $run->export_order, 'fact_hash' => $factHash, 'created_at' => now('UTC')]);
            $observation = DB::table('wald_visit_observations')->insertGetId(['visit_id' => $visitId, 'run_id' => $run->id, 'version' => $version,
                'facts' => Canonical::json($f), 'provenance' => Canonical::json(['workbook_hash' => $run->workbook_hash, 'binding' => $row['binding'],
                    'source_row' => [$row['provenance']['sheet'], $row['provenance']['row']], 'selection' => $row['provenance']['selection'],
                    'semantic_answer' => $row['provenance']['semantic_answer']]), 'fact_hash' => $factHash, 'created_at' => now('UTC'), 'retain_until' => now('UTC')->addYears(6)]);
            DB::table('wald_source_visits')->where('id', $visitId)->update(['epoch' => $version, 'observation_id' => $observation, 'export_order' => $run->export_order, 'fact_hash' => $factHash]);
            $effects[] = ['call_number' => $f['call_number'], 'visit' => $visitId, 'version' => $version, 'service_id' => $service->id, 'outcome' => $diff['outcome']];
        }
        foreach (array_chunk($products, 100) as $chunk) {
            DB::table('projected_plot_products')->upsert($chunk, ['projected_plot_id', 'product_code'], ['quantity', 'last_source_import_run_id', 'updated_at']);
        }
        $epochs = DB::table('projected_plot_services')->whereIn('id', array_column($effects, 'service_id'))->pluck('wald_epoch', 'id');
        foreach ($effects as &$effect) {
            $effect['projection_epoch'] = (int) $epochs[$effect['service_id']];
        } unset($effect);
        $sourceRun->update(['status' => 'completed', 'finished_at' => now(), 'records_applied' => count($effects),
            'records_created' => count(array_filter($effects, fn ($e) => $e['outcome'] === 'added')), 'records_updated' => count(array_filter($effects, fn ($e) => $e['outcome'] === 'changed')),
            'records_unchanged' => count(array_filter($effects, fn ($e) => $e['outcome'] === 'unchanged'))]);

        return ['source_run_uuid' => $sourceRun->uuid, 'counts' => ['seen' => count($rows), 'excluded' => count($rows) - count($included),
            'applied' => count($effects), 'created' => $sourceRun->records_created, 'updated' => $sourceRun->records_updated,
            'unchanged' => $sourceRun->records_unchanged], 'effects' => $effects];
    }
}
