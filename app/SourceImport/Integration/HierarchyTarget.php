<?php

namespace App\SourceImport\Integration;

use Illuminate\Support\Facades\DB;

/** Exact source hierarchy resolution, always scoped to the parsed customer. */
final class HierarchyTarget
{
    public function proposal(array $source): array
    {
        if (! isset($source['hierarchy_mode']) || $source['hierarchy_mode'] === 'LEGACY_FLAT') {
            return ['state' => 'LEGACY_SOURCE'];
        }
        $hierarchy = $source['hierarchy'] ?? [];
        if (($hierarchy['invalid_rows'] ?? 0) > 0 || ($hierarchy['conflicting_rows'] ?? 0) > 0) {
            return ['state' => 'HIERARCHY_REVIEW_REQUIRED'];
        }
        if (! is_string($hierarchy['customer'] ?? null) || ! is_string($hierarchy['site'] ?? null)) {
            return ['state' => 'INVALID_HIERARCHY'];
        }
        $customers = DB::table('customer_organisations')->where('is_active', true)->where('name', $hierarchy['customer'])
            ->get(['id', 'uuid', 'name'])
            ->filter(fn ($record): bool => $record->name === $hierarchy['customer'])->values();
        if ($customers->count() === 0) {
            return ['state' => 'NEW_CUSTOMER_FOUND', 'customer' => $hierarchy['customer'], 'site' => $hierarchy['site']];
        }
        if ($customers->count() !== 1) {
            return ['state' => 'AMBIGUOUS_CUSTOMER'];
        }
        $customer = $customers->first();
        $sites = DB::table('sites')->where('customer_organisation_id', $customer->id)->where('is_active', true)
            ->where('name', $hierarchy['site'])
            ->get(['id', 'uuid', 'name'])->filter(fn ($record): bool => $record->name === $hierarchy['site'])->values();
        if ($sites->count() === 0) {
            return ['state' => 'NEW_SITE_FOUND', 'customer' => $customer->name, 'customer_uuid' => $customer->uuid,
                'site' => $hierarchy['site']];
        }
        if ($sites->count() !== 1) {
            return ['state' => 'AMBIGUOUS_SITE'];
        }
        $site = $sites->first();

        return ['state' => 'EXACT_SITE_FOUND', 'customer' => $customer->name, 'customer_uuid' => $customer->uuid,
            'site' => $site->name, 'site_uuid' => $site->uuid];
    }

    public function assertMatches(array $source, object $site): void
    {
        $proposal = $this->proposal($source);
        if ($proposal['state'] === 'LEGACY_SOURCE') {
            return;
        }
        if ($proposal['state'] !== 'EXACT_SITE_FOUND' || $proposal['site_uuid'] !== $site->uuid) {
            throw new ImportConflict('source_binding_customer_ownership_conflict');
        }
    }
}
