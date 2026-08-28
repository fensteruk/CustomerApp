<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SourceSiteBinding;
use App\Models\User;
use App\Support\ManualSourceImport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SourceSiteBindingService
{
    public function create(User $actor, string $sourceNamespace, string $sourceSiteKey, string $originalName, ?string $displayName, Site $site): SourceSiteBinding
    {
        $this->ensureOfficeStaff($actor);
        $sourceNamespace = $this->normaliseNamespace($sourceNamespace);
        $sourceSiteKey = trim($sourceSiteKey);

        if ($sourceSiteKey === '') {
            throw new \DomainException('The source site key is required.');
        }

        try {
            return DB::transaction(function () use ($actor, $sourceNamespace, $sourceSiteKey, $originalName, $displayName, $site): SourceSiteBinding {
                if (SourceSiteBinding::query()
                    ->where('source_namespace', $sourceNamespace)
                    ->where('source_site_key_hash', SourceSiteBinding::hashFor($sourceSiteKey))
                    ->lockForUpdate()
                    ->exists()) {
                    throw new \DomainException('A binding already exists for this source namespace and exact site key.');
                }

                return SourceSiteBinding::query()->create([
                    'source_namespace' => $sourceNamespace,
                    'source_site_key' => $sourceSiteKey,
                    'source_site_key_hash' => SourceSiteBinding::hashFor($sourceSiteKey),
                    'original_name' => trim($originalName),
                    'display_name' => $displayName === null ? null : trim($displayName),
                    'site_id' => $site->id,
                    'created_by_user_id' => $actor->id,
                ]);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw new \DomainException('A binding already exists for this source namespace and exact site key.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function update(User $actor, SourceSiteBinding $binding, Site $site, ?string $displayName): SourceSiteBinding
    {
        $this->ensureOfficeStaff($actor);

        return DB::transaction(function () use ($binding, $site, $displayName): SourceSiteBinding {
            $binding = SourceSiteBinding::query()->lockForUpdate()->findOrFail($binding->id);
            if ((int) $binding->site_id !== (int) $site->id && $binding->projectedPlots()->exists()) {
                throw new \DomainException('A binding with imported projections cannot be moved to another Portal site. Reconciliation is required.');
            }

            $binding->update([
                'site_id' => $site->id,
                'display_name' => $displayName === null ? null : trim($displayName),
            ]);

            return $binding->fresh(['site.customerOrganisation', 'creator']);
        });
    }

    private function ensureOfficeStaff(User $actor): void
    {
        if (! $actor->hasCompletePortalProfile() || ! $actor->isFensterOfficeStaff() || $actor->is_preview_user) {
            throw new \DomainException('Only active non-preview Fenster Office Staff may manage source-site bindings.');
        }
    }

    private function normaliseNamespace(string $sourceNamespace): string
    {
        $sourceNamespace = mb_strtolower(trim($sourceNamespace));
        if ($sourceNamespace !== ManualSourceImport::SOURCE_NAMESPACE) {
            throw new \DomainException('The source namespace is not supported for manual workbook imports.');
        }

        return $sourceNamespace;
    }
}
