<?php

namespace App\Services;

use App\Enums\SourceImportScope;
use App\Models\ProjectedPlotService;
use App\Models\SourceSiteBinding;

class ManualSourceImportFingerprintService
{
    /**
     * @param  list<string>  $sourceSiteKeys
     * @param  list<string>  $completeSiteKeys
     */
    public function for(
        string $sourceNamespace,
        array $sourceSiteKeys,
        SourceImportScope $scope = SourceImportScope::PartialFilteredExport,
        array $completeSiteKeys = [],
    ): string {
        $keys = collect($sourceSiteKeys)
            ->merge($completeSiteKeys)
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->uniqueStrict()
            ->sort()
            ->values();
        if ($scope === SourceImportScope::GlobalCompleteSnapshot) {
            $keys = SourceSiteBinding::query()
                ->where('source_namespace', $sourceNamespace)
                ->orderBy('source_site_key')
                ->pluck('source_site_key');
        }
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
            ->whereHas('projectedPlot', function ($query) use ($sourceNamespace, $bindingIds, $scope): void {
                $query->where('external_source', $sourceNamespace);
                if ($scope !== SourceImportScope::GlobalCompleteSnapshot) {
                    $query->whereIn('source_site_binding_id', $bindingIds);
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectedPlotService $service): array => [
                'uuid' => $service->uuid,
                'call_number' => $service->source_call_number,
                'call_type' => $service->source_call_type,
                'stage' => $service->source_job_stage,
                'completion_flag' => $service->source_completion_flag,
                'operational_target_date' => $service->source_operational_target_date?->toDateString(),
                'completed_at' => $service->source_completed_at?->toDateString(),
                'present' => $service->source_present,
                'updated_at' => $service->updated_at?->toJSON(),
            ])->all();

        return hash('sha256', json_encode([
            'namespace' => $sourceNamespace,
            'scope' => $scope->value,
            'complete_site_keys' => collect($completeSiteKeys)->map(fn (string $key): string => trim($key))->filter()->uniqueStrict()->sort()->values()->all(),
            'bindings' => $bindings,
            'services' => $services,
        ], JSON_THROW_ON_ERROR));
    }
}
