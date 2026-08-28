<?php

namespace App\Services;

use App\Models\ProjectedPlotService;
use App\Models\SourceSiteBinding;

class ManualSourceImportFingerprintService
{
    /** @param list<string> $sourceSiteKeys */
    public function for(string $sourceNamespace, array $sourceSiteKeys): string
    {
        $keys = collect($sourceSiteKeys)->map(fn (string $key): string => trim($key))->filter()->uniqueStrict()->sort()->values();
        $bindings = $keys->map(function (string $key) use ($sourceNamespace): array {
            $binding = SourceSiteBinding::query()
                ->where('source_namespace', $sourceNamespace)
                ->where('source_site_key_hash', SourceSiteBinding::hashFor($key))
                ->first();

            if ($binding === null || ! hash_equals($binding->source_site_key, $key)) {
                return ['key' => $key, 'binding' => null, 'site' => null, 'updated_at' => null];
            }

            return [
                'key' => $key,
                'binding' => $binding->uuid,
                'site' => $binding->site_id,
                'updated_at' => $binding->updated_at?->toJSON(),
            ];
        })->all();

        $bindingIds = SourceSiteBinding::query()
            ->where('source_namespace', $sourceNamespace)
            ->whereIn('source_site_key_hash', $keys->map(fn (string $key): string => SourceSiteBinding::hashFor($key)))
            ->pluck('id');
        $services = ProjectedPlotService::query()
            ->whereHas('projectedPlot', fn ($query) => $query
                ->where('external_source', $sourceNamespace)
                ->whereIn('source_site_binding_id', $bindingIds))
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectedPlotService $service): array => [
                'uuid' => $service->uuid,
                'call_number' => $service->source_call_number,
                'call_type' => $service->source_call_type,
                'stage' => $service->source_job_stage,
                'completed_at' => $service->source_completed_at?->toDateString(),
                'present' => $service->source_present,
                'updated_at' => $service->updated_at?->toJSON(),
            ])->all();

        return hash('sha256', json_encode([
            'namespace' => $sourceNamespace,
            'bindings' => $bindings,
            'services' => $services,
        ], JSON_THROW_ON_ERROR));
    }
}
