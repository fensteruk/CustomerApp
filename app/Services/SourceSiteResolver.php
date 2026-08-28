<?php

namespace App\Services;

use App\Data\SourceSiteResolution;
use App\Models\Site;
use App\Models\SourceSiteBinding;
use App\Support\ManualSourceImport;

class SourceSiteResolver
{
    public function resolve(string $sourceNamespace, string $sourceSiteKey): ?SourceSiteResolution
    {
        $sourceNamespace = mb_strtolower(trim($sourceNamespace));
        $sourceSiteKey = trim($sourceSiteKey);

        if ($sourceNamespace === '' || $sourceSiteKey === '') {
            return null;
        }

        $binding = SourceSiteBinding::query()
            ->with('site.customerOrganisation')
            ->where('source_namespace', $sourceNamespace)
            ->where('source_site_key_hash', SourceSiteBinding::hashFor($sourceSiteKey))
            ->first();

        if ($binding !== null) {
            if (! hash_equals($binding->source_site_key, $sourceSiteKey)) {
                return null;
            }

            return new SourceSiteResolution($binding->site, $binding);
        }

        if ($sourceNamespace === ManualSourceImport::SOURCE_NAMESPACE) {
            return null;
        }

        $legacySite = Site::query()
            ->where('external_source', $sourceNamespace)
            ->where('external_identifier', $sourceSiteKey)
            ->first();

        return $legacySite === null ? null : new SourceSiteResolution($legacySite, null);
    }
}
