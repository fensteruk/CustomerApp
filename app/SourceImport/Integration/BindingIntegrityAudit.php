<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use Illuminate\Support\Facades\DB;

/** Read-only integrity inspection. An absent partial-export row never invalidates a binding. */
final class BindingIntegrityAudit
{
    public const BLOCKING = ['CUSTOMER_MISMATCH', 'SITE_MISMATCH', 'DUPLICATE_ACTIVE_BINDING',
        'MISSING_TARGET', 'ORPHAN_BINDING_VERSION', 'SOURCE_HIERARCHY_CONFLICT'];

    public function run(): array
    {
        return DB::transactionLevel() > 0 ? $this->inspect() : DB::transaction(fn (): array => $this->inspect());
    }

    private function inspect(): array
    {
        $evidence = $this->evidence();
        $counts = [];
        DB::table('wald_source_bindings')->select(['id', 'source_namespace', 'source_identity'])->where('identity_kind', 'CUSTOMER_CODE')->whereNotNull('active_version')->orderBy('id')
            ->chunkById(400, function ($roots) use (&$counts): void {
                foreach ($roots as $root) {
                    $key = $root->source_namespace."\0".$root->source_identity;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            });
        $duplicateKeys = array_filter($counts, fn ($count): bool => $count > 1);
        $items = [];
        DB::table('wald_source_bindings')->where('identity_kind', 'CUSTOMER_CODE')->orderBy('id')
            ->chunkById(200, function ($roots) use (&$items, $evidence, $duplicateKeys): void {
                $versions = DB::table('wald_binding_versions as v')->join('wald_source_bindings as r', 'r.id', '=', 'v.binding_id')
                    ->whereIn('v.binding_id', $roots->pluck('id'))->where(fn ($query) => $query
                    ->whereColumn('v.version', 'r.active_version')->orWhereColumn('v.version', 'r.latest_version'))
                    ->get(['v.*'])->groupBy('binding_id');
                $historyHeads = DB::table('wald_binding_versions')->whereIn('binding_id', $roots->pluck('id'))
                    ->select('binding_id')->selectRaw('MAX(version) as maximum_version')->groupBy('binding_id')->pluck('maximum_version', 'binding_id');
                $all = $versions->flatten(1);
                $sites = DB::table('sites')->whereIn('id', $all->pluck('site_id')->unique())->get()->keyBy('id');
                $customers = DB::table('customer_organisations')->whereIn('id', $all->pluck('customer_organisation_id')->unique())->get()->keyBy('id');
                foreach ($roots as $root) {
                    $history = $versions->get($root->id, collect());
                    $active = $history->where('version', $root->active_version);
                    if ($root->active_version === null) {
                        if ((int) $root->latest_version !== (int) $historyHeads->get($root->id, 0)) {
                            $items[] = $this->item($root, null, null, null, 'ORPHAN_BINDING_VERSION', 'The latest binding version is missing.');
                        }

                        continue;
                    }
                    $version = $active->first();
                    $site = $version ? $sites->get($version->site_id) : null;
                    $customer = $version ? $customers->get($version->customer_organisation_id) : null;
                    $key = $root->source_namespace."\0".$root->source_identity;
                    $source = $evidence[$key] ?? null;
                    $issue = match (true) {
                        $active->count() > 1 || isset($duplicateKeys[$key]) => ['DUPLICATE_ACTIVE_BINDING', 'More than one active target was found.'],
                        $version === null || (int) $root->latest_version !== (int) $historyHeads->get($root->id, 0) || $root->active_version > $root->latest_version || $root->active_version <= $root->revoked_through
                            || ! $history->contains('version', $root->latest_version) => ['ORPHAN_BINDING_VERSION', 'The active/latest/revoked binding history is inconsistent.'],
                        $site === null || $customer === null => ['MISSING_TARGET', 'The bound customer or site no longer exists.'],
                        (int) $site->customer_organisation_id !== (int) $customer->id => ['CUSTOMER_MISMATCH', 'The site does not belong to the bound customer.'],
                        ! $site->is_active || ! $customer->is_active => ['MISSING_TARGET', 'The target customer or site is inactive and cannot receive imports.'],
                        ! hash_equals(Canonical::hash([$root->source_namespace, $root->identity_kind, $root->source_identity]), $root->identity_hash)
                            || ! hash_equals(Canonical::hash(['identity_hash' => $root->identity_hash, 'version' => (int) $version->version,
                                'organisation' => (int) $version->customer_organisation_id, 'site' => (int) $version->site_id]), $version->definition_hash) => ['ORPHAN_BINDING_VERSION', 'The binding identity or version fingerprint is inconsistent.'],
                        default => $this->hierarchy($source, $customer, $site),
                    };
                    $items[] = $this->item($root, $site, $customer, $source, ...$issue);
                }
            });
        DB::table('wald_binding_versions as versions')->leftJoin('wald_source_bindings as roots', 'roots.id', '=', 'versions.binding_id')
            ->whereNull('roots.id')->orderBy('versions.id')->chunk(200, function ($versions) use (&$items): void {
                foreach ($versions as $version) {
                    $items[] = ['customer_code' => 'Unavailable', 'namespace' => null, 'binding_uuid' => null, 'customer' => null, 'site' => null,
                        'source' => null, 'upload_uuid' => null, 'category' => 'ORPHAN_BINDING_VERSION', 'blocking' => true,
                        'reason' => 'A binding version has no retained binding root.', 'action' => 'Needs Office review'];
                }
            });
        usort($items, fn ($a, $b): int => strcmp($a['customer_code'], $b['customer_code']) ?: strcmp($a['binding_uuid'] ?? '', $b['binding_uuid'] ?? ''));
        $categories = array_count_values(array_column($items, 'category'));

        return ['items' => $items, 'categories' => $categories, 'healthy' => $categories['HEALTHY'] ?? 0,
            'blocking' => count(array_filter($items, fn ($item): bool => $item['blocking'])),
            'warnings' => count(array_filter($items, fn ($item): bool => ! $item['blocking'] && $item['category'] !== 'HEALTHY'))];
    }

    private function evidence(): array
    {
        $sources = [];
        $streams = DB::table('wald_import_streams')->get(['id', 'source_namespace']);
        foreach ($streams as $stream) {
            // One newest retained revision per stream, never an unbounded manifest history scan.
            $upload = DB::table('wald_pilot_uploads')->where('stream_id', $stream->id)
                ->orderByDesc('export_order')->orderByDesc('revision')->first();
            if (! $upload || ! $upload->source_manifest) {
                continue;
            }
            $manifest = json_decode($upload->source_manifest, true);
            if (! is_array($manifest)) {
                continue;
            }
            $fresh = ($manifest['schema'] ?? null) === PilotWorkbookDiscovery::SCHEMA
                && ($manifest['knowledge_policy'] ?? null) === KnowledgeIdentity::POLICY
                && $upload->state !== 'SUPERSEDED' && $upload->state !== 'FAILED'
                && is_string($upload->source_manifest_hash)
                && hash_equals(Canonical::hash($manifest), $upload->source_manifest_hash);
            foreach ($manifest['sources'] ?? [] as $source) {
                if (($source['kind'] ?? null) === 'CUSTOMER_CODE') {
                    $sources[$stream->source_namespace."\0".$source['identity']] = [
                        'fresh' => $fresh, 'hierarchy' => $source['hierarchy'] ?? null,
                        'upload_uuid' => $upload->uuid, 'export_order' => $upload->export_order,
                    ];
                }
            }
        }

        return $sources;
    }

    private function hierarchy(?array $source, object $customer, object $site): array
    {
        if ($source === null || ! is_array($source['hierarchy'])) {
            return ['UNVERIFIABLE', 'No current parsed customer/site evidence is available. Absence in a partial export does not invalidate this binding.'];
        }
        if (! $source['fresh']) {
            return ['STALE_SOURCE_EVIDENCE', 'Retained source evidence needs analysis with the current dictionary and structure rules.'];
        }
        $hierarchy = $source['hierarchy'];
        if (($hierarchy['invalid_rows'] ?? 0) > 0 || ($hierarchy['conflicting_rows'] ?? 0) > 0) {
            return ['SOURCE_HIERARCHY_CONFLICT', 'Included source rows do not establish one consistent customer/site.'];
        }
        if (! is_string($hierarchy['customer'] ?? null) || ! is_string($hierarchy['site'] ?? null)) {
            return ['UNVERIFIABLE', 'The source does not provide a complete parsed hierarchy.'];
        }
        if (! MasterSourceResolver::sameName($hierarchy['customer'], $customer->name)) {
            return ['CUSTOMER_MISMATCH', 'The parsed source customer differs from the bound customer.'];
        }
        if (! MasterSourceResolver::sameName($hierarchy['site'], $site->name)) {
            return ['SITE_MISMATCH', 'The parsed source site differs from the bound site. Review any upload-scoped name confirmation.'];
        }

        return ['HEALTHY', 'Exact source hierarchy, active binding and customer/site ownership agree.'];
    }

    private function item(object $root, ?object $site, ?object $customer, ?array $source, string $category, string $reason): array
    {
        return ['customer_code' => $root->source_identity, 'namespace' => $root->source_namespace, 'binding_uuid' => $root->uuid,
            'customer' => $customer?->name, 'site' => $site?->name, 'source' => $source['hierarchy'] ?? null,
            'upload_uuid' => $source['upload_uuid'] ?? null, 'category' => $category,
            'blocking' => in_array($category, self::BLOCKING, true), 'reason' => $reason,
            'action' => $category === 'HEALTHY' ? 'No action required' : 'Needs Office review'];
    }
}
