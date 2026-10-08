<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\Wald\Services\ValueProfiler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Fresh transient analysis only. No import command, binding, knowledge or projection persistence. */
final class WaldDryRun
{
    public function analyse(User $actor, UploadedFile $file): array
    {
        (new PilotImportPolicy)->authorize($actor);
        $start = hrtime(true);
        $storage = new PrivateWorkbookStorage;
        $artifact = $storage->store($file);
        try {
            $inspection = (new WorkbookStager)->inspect((object) $artifact);
            try {
                $manifest = (new PilotWorkbookDiscovery)->inspect((object) $artifact, inspection: $inspection);
            } catch (ImportConflict $conflict) {
                if (in_array($conflict->getMessage(), ['required_call_column_unresolved', 'required_call_type_column_unresolved',
                    'customer_code_missing', 'customer_code_column_ambiguous', 'source_site_name_column_ambiguous'], true)) {
                    throw DryRunHeaderConflict::fromInspection($conflict, $inspection);
                }

                throw $conflict;
            }
            // Current database state is observed in one read transaction. No saved profile is activated.
            $report = DB::transaction(function () use ($actor, $artifact, $inspection, $manifest): array {
                (new PilotImportPolicy)->authorize($actor);
                $resolutions = (new MasterSourceResolver)->resolve($manifest['sources'], 'redzebra');
                $audit = (new BindingIntegrityAudit)->run();
                $checks = $this->facts($inspection, $manifest, $artifact['workbook_hash']);
                $current = $this->currentFacts($manifest, $resolutions);
                $units = [];
                $automatic = 0;
                $blocked = 0;
                $plotCreate = 0;
                $plotReuse = 0;
                foreach ($manifest['sources'] as $source) {
                    $resolution = $resolutions[$source['hash']];
                    $issues = $checks[$source['hash']]['issues'] ?? [];
                    $issues = [...$issues, ...($current['issues'][$source['hash']] ?? [])];
                    if (in_array($resolution['state'], ['BINDING_CONFLICT', 'SOURCE_HIERARCHY_CONFLICT', 'MALFORMED_HIERARCHY'], true)) {
                        $issues[] = $resolution['state'];
                    }
                    $integrity = array_values(array_filter($audit['items'], fn ($item): bool => $item['namespace'] === 'redzebra' && $item['customer_code'] === $source['identity']));
                    foreach ($integrity as $item) {
                        if ($item['blocking']) {
                            $issues[] = 'BINDING_INTEGRITY_'.$item['category'];
                        }
                    }
                    if ($manifest['requires_confirmation']) {
                        $issues[] = 'HEADER_REVIEW_REQUIRED';
                    }
                    $issues = array_values(array_unique($issues));
                    sort($issues, SORT_STRING);
                    $matched = in_array($resolution['state'], ['EXACT_EXISTING_BINDING', 'EXACT_CUSTOMER_EXACT_SITE'], true);
                    $automatic += $matched && $issues === [] ? 1 : 0;
                    $blocked += $issues !== [] ? 1 : 0;
                    $plotCreate += $resolution['plots_create'];
                    $plotReuse += $resolution['plots_reuse'];
                    $units[] = ['customer_code' => $source['customer_code'] ?? $source['identity'],
                        'state' => $resolution['state'], 'customer' => $resolution['customer'] ?? null,
                        'site' => $resolution['site'] ?? null, 'rows' => $source['rows'],
                        'plot_count' => $resolution['plot_count'], 'plots_create' => $resolution['plots_create'],
                        'plots_reuse' => $resolution['plots_reuse'], 'issues' => $issues,
                        'integrity' => $integrity, 'product_conflicts' => $checks[$source['hash']]['product_conflicts'] ?? [],
                        'withheld_products' => $checks[$source['hash']]['withheld_products'] ?? [],
                        'site_uuid' => $resolution['site_uuid'] ?? null,
                        'binding' => $resolution['binding'], 'example_plots' => $resolution['example_plots']];
                }
                usort($units, fn ($a, $b): int => strcmp($a['customer_code'], $b['customer_code']));
                $states = array_count_values(array_column($units, 'state'));
                ksort($states, SORT_STRING);
                $content = ['schema' => 'customerapp.wald-dry-run.v2', 'workbook_hash' => $artifact['workbook_hash'],
                    'pins' => (new KnowledgeIdentity)->current(), 'analysis_hash' => $manifest['analysis_hash'],
                    'guarantee' => 'NO_BUSINESS_OR_BINDING_MUTATIONS', 'apply_approval' => false,
                    'current_state_hash' => $current['hash'],
                    'capabilities' => $manifest['capabilities'],
                    'summary' => [...$manifest['row_summary'], 'plots_discovered' => array_sum(array_column($units, 'plot_count')),
                        'total_rows' => $manifest['record_count'], 'included' => $manifest['included_count'],
                        'excluded' => $manifest['excluded_count'], 'source_units' => count($units),
                        'automatic' => $automatic, 'manual_review' => count($units) - $automatic,
                        'blocking_units' => $blocked, 'unknown_rows' => $manifest['unclassified_count'],
                        'malformed_groups' => $states['MALFORMED_HIERARCHY'] ?? 0,
                        'hierarchy_conflicts' => $states['SOURCE_HIERARCHY_CONFLICT'] ?? 0,
                        'existing_bindings' => $states['EXACT_EXISTING_BINDING'] ?? 0,
                        'exact_matches_without_binding' => $states['EXACT_CUSTOMER_EXACT_SITE'] ?? 0,
                        'new_site_proposals' => $states['EXACT_CUSTOMER_NEW_SITE'] ?? 0,
                        'new_customer_site_proposals' => $states['NEW_CUSTOMER_AND_SITE'] ?? 0,
                        'plots_create' => $plotCreate, 'plots_reuse' => $plotReuse],
                    'outcomes' => $states, 'units' => $units,
                    'unknown_rows' => array_values(array_filter($manifest['review_rows'], fn ($row): bool => $row['source_identity_hash'] === null)),
                    'ignored_customer_codes' => $manifest['ignored_rows'],
                    'call_findings' => $manifest['call_findings'],
                    'integrity_summary' => array_intersect_key($audit, array_flip(['healthy', 'warnings', 'blocking']))];

                return ['content' => $content, 'fingerprint' => Canonical::evidenceHash($content)];
            });

            WorkbookAnalysisCapacity::guardDuration($start);

            return [...$report, 'metrics' => ['duration_ms' => round((hrtime(true) - $start) / 1_000_000, 1),
                'peak_memory_bytes' => memory_get_peak_usage(true)]];
        } finally {
            $storage->discardUnregistered($artifact['storage_key']);
        }
    }

    private function facts(array $inspection, array $manifest, string $workbookHash): array
    {
        [, $sheets, $snapshot] = $inspection;
        $sheet = array_values($sheets)[0];
        $columns = [];
        foreach ($snapshot->data['questions'] as $key => $question) {
            if (($question['type'] ?? null) !== 'STRUCTURAL' || $key === 'structure:ignored') {
                continue;
            }
            if (count($question['candidates'] ?? []) !== 1) {
                throw new ImportConflict('dry_run_column_ambiguous');
            }
            $candidate = $question['candidates'][0];
            $columns[$candidate['role']] = $candidate['column'];
        }
        $dictionary = new CustomerAppDictionary;
        $rows = [];
        $result = [];
        foreach ($manifest['review_rows'] as $row) {
            $hash = $row['source_identity_hash'];
            if ($hash === null) {
                continue;
            }
            $cells = $sheet->cells[$row['row_number']];
            $selection = (new ReviewedWorkbookSelection)->treatment($workbookHash, $sheet->id,
                $row['row_number'], $row['call_no'], $row['source_site_name'] ?? $row['customer_code'], $row['call_type'] ?? '');
            $type = $dictionary->callType($selection['canonical_override'] ?? $row['call_type'] ?? '',
                ($snapshot->data['tables'][array_key_first($snapshot->data['tables'])]['composite'] ?? false) ? CustomerAppDictionary::COMPOSITE_PROFILE : null);
            $issues = [];
            $finding = collect($manifest['call_findings'])->firstWhere('call_number', $row['call_no']);
            $callBlockers = ScopedCallEvidence::marker($finding);
            if ($callBlockers !== []) {
                $issues[] = 'DUPLICATE_CALL_NUMBER';
            }
            if ($type->isResolved() && $type->value !== null && $row['call_no'] === '') {
                $issues[] = 'MISSING_CALLOFF_REFERENCE';
            }
            if (! $type->isResolved()) {
                $issues[] = 'UNKNOWN_CALL_TYPE';
            }
            if ($row['issue'] !== null) {
                $issues[] = $row['issue'];
            }
            if (($row['call_type'] ?? '') !== '' && isset($columns['completion'])
                && $callBlockers === []
                && ! $dictionary->completion($cells[$columns['completion']]->rawValue ?? null)->isResolved()) {
                $issues[] = 'UNKNOWN_COMPLETION';
            }
            $products = [];
            foreach ($columns as $role => $column) {
                if (! str_starts_with($role, 'quantity:')) {
                    continue;
                }
                $cell = $cells[$column] ?? null;
                $value = $cell?->rawValue;
                if (! $cell || is_string($value) && trim($value) === '') {
                    continue;
                }
                $product = $dictionary->product(substr($role, 9), $value);
                if (($product->match['group'] ?? null) === 'EXCLUDED') {
                    continue;
                }
                if (! $product->isResolved() || in_array((new ValueProfiler)->type($cell), ['date_like', 'percentage_like', 'boolean_like', 'formula', 'error'], true)) {
                    $issues[] = 'INVALID_PRODUCT_QUANTITY';
                } else {
                    $products[substr($role, 9)] = $product->value;
                }
            }
            if (! $dictionary->rollup($products)->resolved) {
                $issues[] = 'PRODUCT_ROLLUP_INVALID';
            }
            $rows[$hash][] = ['excluded' => false, 'call_blockers' => $callBlockers, 'canonical' => [], 'facts' => ['call_number' => $row['call_no'],
                'plot' => $row['parsed_plot'] ?? $row['raw_plot_ref'], 'products' => $products,
                'call_type' => $type->lookupValue, 'service' => $type->value]];
            $result[$hash]['issues'] = [...($result[$hash]['issues'] ?? []), ...$issues];
        }
        foreach ($rows as $hash => $facts) {
            $result[$hash]['withheld_products'] = ScopedCallEvidence::partitionProducts($facts);
            $facts = array_map(fn ($fact) => [...$fact, 'facts' => ScopedCallEvidence::facts($fact)], $facts);
            $products = (new ProductConsolidator)->consolidate($facts);
            $result[$hash]['product_conflicts'] = $products['conflicts'];
            if ($products['conflicts'] !== []) {
                $result[$hash]['issues'][] = 'PRODUCT_QUANTITY_CONFLICT';
            }
            $visits = [];
            foreach ($facts as $fact) {
                if ($fact['facts']['service'] !== null) {
                    $key = $fact['facts']['plot']."\0".$fact['facts']['service'];
                    $visits[$key][$fact['facts']['call_number']] = true;
                }
            }
            if (array_filter($visits, fn ($calls): bool => count($calls) > 1) !== []) {
                $result[$hash]['issues'][] = 'DISTINCT_VISIT_PROJECTION_REQUIRES_REVIEW';
            }
        }

        return $result;
    }

    private function currentFacts(array $manifest, array $resolutions): array
    {
        $issues = [];
        $digest = hash_init('sha256');
        $sourceRows = [];
        $calls = array_column($manifest['review_rows'], 'call_no');
        foreach (array_chunk($calls, 400) as $chunk) {
            foreach (DB::table('wald_source_rows')->where('source_namespace', 'redzebra')->whereIn('call_number', $chunk)->orderBy('id')->get() as $row) {
                $sourceRows[$row->call_number] = $row;
                hash_update($digest, Canonical::json((array) $row));
            }
        }
        $sites = array_values(array_unique(array_filter(array_column($resolutions, 'site_id'))));
        $needed = [];
        $references = [];
        foreach ($manifest['sources'] as $source) {
            $siteId = $resolutions[$source['hash']]['site_id'] ?? null;
            if ($siteId === null) {
                continue;
            }
            foreach ($source['plots'] ?? [] as $reference) {
                $needed[$siteId][$reference] = true;
                $references[$reference] = true;
            }
        }
        $plots = [];
        $services = [];
        foreach (array_chunk($sites, 200) as $chunk) {
            foreach (array_chunk(array_keys($references), 400) as $referenceChunk) {
                DB::table('projected_plots')->whereIn('site_id', $chunk)->whereIn('plot_reference', $referenceChunk)
                    ->orderBy('id')->chunkById(400, function ($records) use (&$plots, &$services, $digest, $needed): void {
                        $records = $records->filter(fn ($plot): bool => isset($needed[$plot->site_id][$plot->plot_reference]));
                        foreach ($records as $plot) {
                            $plots[$plot->site_id."\0".$plot->plot_reference] = $plot;
                            hash_update($digest, Canonical::json((array) $plot));
                        }
                        foreach (DB::table('projected_plot_services')->whereIn('projected_plot_id', $records->pluck('id'))->orderBy('id')->get() as $service) {
                            $services[$service->projected_plot_id."\0".$service->service_identifier] = $service;
                            hash_update($digest, Canonical::json((array) $service));
                        }
                        foreach (DB::table('projected_plot_products')->whereIn('projected_plot_id', $records->pluck('id'))->orderBy('id')->get() as $product) {
                            hash_update($digest, Canonical::json((array) $product));
                        }
                    });
            }
        }
        $dictionary = new CustomerAppDictionary;
        foreach ($manifest['review_rows'] as $row) {
            if (collect($manifest['call_findings'])->contains('call_number', $row['call_no'])) {
                continue;
            }
            $hash = $row['source_identity_hash'];
            $resolution = $resolutions[$hash] ?? null;
            if ($resolution === null || ! isset($resolution['site_id']) || $row['parsed_plot'] === null) {
                continue;
            }
            $existing = $sourceRows[$row['call_no']] ?? null;
            if ($existing && ((int) $existing->site_id !== (int) $resolution['site_id'] || $existing->plot_reference !== $row['parsed_plot'])) {
                $issues[$hash][] = 'SOURCE_ROW_IDENTITY_CHANGED';
            }
            $plot = $plots[$resolution['site_id']."\0".$row['parsed_plot']] ?? null;
            $serviceType = $dictionary->callType($row['call_type'] ?? '');
            $service = $plot && $serviceType->isResolved() && $serviceType->value !== null
                ? ($services[$plot->id."\0".$serviceType->value] ?? null) : null;
            if ($service && $service->source_call_number !== null && $service->source_call_number !== $row['call_no']) {
                $issues[$hash][] = 'DISTINCT_VISIT_PROJECTION_REQUIRES_REVIEW';
            }
        }

        return ['issues' => $issues, 'hash' => hash_final($digest)];
    }
}
