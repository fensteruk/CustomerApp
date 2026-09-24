<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use Illuminate\Support\Facades\DB;

/** Read-only, workbook-wide resolution of distinct source identities. */
final class MasterSourceResolver
{
    public const VERSION = 'customerapp.master-source-resolution.v1';

    public function resolve(array $sources, string $namespace): array
    {
        $customers = DB::table('customer_organisations')->where('is_active', true)->get(['id', 'uuid', 'name']);
        $sites = DB::table('sites')->where('is_active', true)->get(['id', 'uuid', 'name', 'customer_organisation_id']);
        $customersById = $customers->keyBy('id');
        $sitesById = $sites->keyBy('id');
        $customerNames = [];
        foreach ($customers as $customer) {
            $customerNames[self::normalizedName($customer->name)][] = $customer;
        }
        $siteNames = [];
        foreach ($sites as $site) {
            $siteNames[$site->customer_organisation_id][self::normalizedName($site->name)][] = $site;
        }

        $identityHashes = array_map(fn (array $source): string => Canonical::hash([
            $namespace, $source['kind'], $source['identity'],
        ]), $sources);
        $roots = [];
        foreach (array_chunk($identityHashes, 400) as $chunk) {
            foreach (DB::table('wald_source_bindings')->whereIn('identity_hash', $chunk)->get() as $root) {
                $roots[$root->identity_hash] = $root;
            }
        }
        $versions = [];
        foreach (array_chunk(array_map(fn (object $root): int => (int) $root->id, array_values($roots)), 400) as $chunk) {
            foreach (DB::table('wald_binding_versions')->whereIn('binding_id', $chunk)->get() as $version) {
                $versions[$version->binding_id][$version->version] = $version;
            }
        }

        $resolved = [];
        $plotSites = [];
        foreach ($sources as $source) {
            $hash = $source['hash'];
            $identityHash = Canonical::hash([$namespace, $source['kind'], $source['identity']]);
            $root = $roots[$identityHash] ?? null;
            $active = $root && $root->active_version !== null ? ($versions[$root->id][$root->active_version] ?? null) : null;
            $latest = $root && $root->latest_version > 0 ? ($versions[$root->id][$root->latest_version] ?? null) : null;
            $binding = $this->binding($root, $active, $sitesById, $customersById);
            $draft = $this->binding($root, $latest, $sitesById, $customersById);
            $plots = array_values(array_unique($source['plots'] ?? []));
            $base = [
                'customer_code' => $source['customer_code'] ?? null,
                'rows' => (int) ($source['rows'] ?? 0),
                'plot_count' => count($plots),
                'example_plots' => array_slice($plots, 0, 5),
                'plots_reuse' => 0, 'plots_create' => 0,
                'source_evidence' => ($source['hierarchy']['conflicting_rows'] ?? 0) > 0
                    ? ($source['hierarchy_variants'] ?? []) : [],
                'binding' => $binding, 'draft' => $draft,
            ];
            if (($source['hierarchy_mode'] ?? null) !== 'COMPOSITE') {
                $resolved[$hash] = ['state' => 'LEGACY_SOURCE', ...$base];

                continue;
            }
            $hierarchy = $source['hierarchy'] ?? [];
            if (($hierarchy['invalid_rows'] ?? 0) > 0 || ! is_string($hierarchy['customer'] ?? null)
                || ! is_string($hierarchy['site'] ?? null)) {
                $resolved[$hash] = ['state' => 'MALFORMED_HIERARCHY', 'customer' => $hierarchy['customer'] ?? null,
                    'site' => $hierarchy['site'] ?? null, 'issues' => $source['hierarchy_issues'] ?? [], ...$base];

                continue;
            }
            if (($hierarchy['conflicting_rows'] ?? 0) > 0) {
                $resolved[$hash] = ['state' => 'SOURCE_HIERARCHY_CONFLICT', 'customer' => $hierarchy['customer'],
                    'site' => $hierarchy['site'], ...$base];

                continue;
            }
            $compatibleDraft = $root !== null && $root->active_version === null && $draft !== null
                && self::sameName($draft['customer_name'], $hierarchy['customer'])
                && self::sameName($draft['site_name'], $hierarchy['site']);
            if ($root && ! $compatibleDraft && ($active === null || $binding === null
                || ! self::sameName($binding['customer_name'], $hierarchy['customer'])
                || ! self::sameName($binding['site_name'], $hierarchy['site']))) {
                $resolved[$hash] = ['state' => 'BINDING_CONFLICT',
                    'reason' => $active ? 'binding_target_mismatch' : 'binding_not_active',
                    'customer' => $hierarchy['customer'], 'site' => $hierarchy['site'], ...$base];

                continue;
            }
            $customerMatches = $customerNames[self::normalizedName($hierarchy['customer'])] ?? [];
            if (count($customerMatches) > 1) {
                $resolved[$hash] = ['state' => 'BINDING_CONFLICT', 'reason' => 'ambiguous_customer_name',
                    'customer' => $hierarchy['customer'], 'site' => $hierarchy['site'], ...$base];

                continue;
            }
            if ($customerMatches === []) {
                $resolved[$hash] = ['state' => 'NEW_CUSTOMER_AND_SITE', 'customer' => $hierarchy['customer'],
                    'site' => $hierarchy['site'], ...$base, 'plots_create' => count($plots)];

                continue;
            }
            $customer = $customerMatches[0];
            $siteMatches = $siteNames[$customer->id][self::normalizedName($hierarchy['site'])] ?? [];
            if (count($siteMatches) > 1) {
                $resolved[$hash] = ['state' => 'BINDING_CONFLICT', 'reason' => 'ambiguous_site_name',
                    'customer' => $customer->name, 'site' => $hierarchy['site'], ...$base];

                continue;
            }
            if ($siteMatches === []) {
                $resolved[$hash] = ['state' => 'EXACT_CUSTOMER_NEW_SITE', 'customer' => $customer->name,
                    'customer_id' => $customer->id, 'customer_uuid' => $customer->uuid,
                    'site' => $hierarchy['site'], ...$base, 'plots_create' => count($plots)];

                continue;
            }
            $site = $siteMatches[0];
            $target = ['customer' => $customer->name, 'customer_id' => $customer->id,
                'customer_uuid' => $customer->uuid, 'site' => $site->name,
                'site_id' => $site->id, 'site_uuid' => $site->uuid];
            if ($root && ((! $compatibleDraft && $active === null)
                || ($active !== null && ((int) $active->site_id !== (int) $site->id
                    || (int) $active->customer_organisation_id !== (int) $customer->id)))) {
                $resolved[$hash] = ['state' => 'BINDING_CONFLICT', 'reason' => $active ? 'binding_target_mismatch' : 'binding_not_active',
                    ...$target, ...$base];

                continue;
            }
            $state = $active ? 'EXACT_EXISTING_BINDING' : 'EXACT_CUSTOMER_EXACT_SITE';
            $resolved[$hash] = ['state' => $state, ...$target, ...$base];
            $plotSites[$site->id] = true;
        }

        $existingPlots = [];
        foreach (array_chunk(array_keys($plotSites), 400) as $chunk) {
            foreach (DB::table('projected_plots')->whereIn('site_id', $chunk)->get(['site_id', 'plot_reference']) as $plot) {
                $existingPlots[$plot->site_id][$plot->plot_reference] = true;
            }
        }
        foreach ($sources as $source) {
            $resolution = &$resolved[$source['hash']];
            if (! in_array($resolution['state'], ['EXACT_EXISTING_BINDING', 'EXACT_CUSTOMER_EXACT_SITE'], true)) {
                unset($resolution);

                continue;
            }
            foreach (array_unique($source['plots'] ?? []) as $plot) {
                if (isset($existingPlots[$resolution['site_id']][$plot])) {
                    $resolution['plots_reuse']++;
                } else {
                    $resolution['plots_create']++;
                }
            }
            unset($resolution);
        }

        return $resolved;
    }

    public static function sameName(string $left, string $right): bool
    {
        return self::normalizedName($left) === self::normalizedName($right);
    }

    public static function normalizedName(string $value): string
    {
        return mb_strtolower(SourceIdentity::plotReference($value));
    }

    private function binding(?object $root, ?object $version, $sites, $customers): ?array
    {
        if (! $root || ! $version) {
            return null;
        }
        $site = $sites[$version->site_id] ?? null;
        $customer = $customers[$version->customer_organisation_id] ?? null;
        if (! $site || ! $customer || (int) $site->customer_organisation_id !== (int) $customer->id) {
            return null;
        }

        return ['uuid' => $root->uuid, 'epoch' => (int) $root->epoch,
            'version' => (int) $version->version, 'definition_hash' => $version->definition_hash,
            'site_uuid' => $site->uuid, 'site_name' => $site->name, 'customer_name' => $customer->name];
    }
}
