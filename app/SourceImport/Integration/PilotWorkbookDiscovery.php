<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

/** Structural discovery only; the controlled dictionary remains the sole meaning authority. */
final class PilotWorkbookDiscovery
{
    public const MAX_PARENT_ROWS = 5000;

    public function inspect(object $upload): array
    {
        [, $sheets, $snapshot] = (new WorkbookStager)->inspect($upload);
        $data = $snapshot->data;
        if (($data['composite_candidate_count'] ?? 0) > 1) {
            throw new ImportConflict('ambiguous_composite_table');
        }
        if (count($data['tables']) !== 1 || count($sheets) !== 1) {
            throw new ImportConflict('bounded_single_table_required');
        }
        $tableId = array_key_first($data['tables']);
        $table = $data['tables'][$tableId];
        $sheet = array_values($sheets)[0];
        if (array_diff($table['warnings'], ['no_clear_header']) !== [] || $sheet->visibility !== 'visible'
            || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
            throw new ImportConflict('unsafe_or_unsupported_structure');
        }

        $roles = [];
        foreach ($data['questions'] as $question) {
            if (($question['type'] ?? null) !== 'STRUCTURAL') {
                continue;
            }
            foreach ($question['candidates'] ?? [] as $candidate) {
                if (($candidate['table'] ?? null) === $tableId) {
                    $roles[$candidate['role']][$candidate['column']] = true;
                }
            }
        }
        $callColumn = $this->single($roles, 'call_reference', 'required_call_column_unresolved');
        $callTypeColumn = $this->single($roles, 'call_type', 'required_call_type_column_unresolved');
        $plotColumns = array_keys($roles['plot_reference'] ?? []);
        $plotColumn = count($plotColumns) === 1 ? (int) $plotColumns[0] : null;
        $identity = (new MasterExportSiteIdentity)->columns($roles, $upload->workbook_hash);

        $seen = [];
        $sources = [];
        $included = 0;
        $excluded = 0;
        $sawHierarchySignal = false;
        $dataStart = (int) ($table['data_start_row'] ?? ($table['header_range']['end_row'] + 1));
        $dataEnd = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataEnd
                || ! array_filter($cells, fn ($cell): bool => $cell->rawValue !== null && $cell->rawValue !== '')) {
                continue;
            }
            if (count($seen) >= self::MAX_PARENT_ROWS) {
                throw new ImportConflict('pilot_workbook_row_limit_exceeded');
            }
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    throw new ImportConflict('unsafe_source_cell');
                }
            }
            $call = (string) ($cells[$callColumn]->rawValue ?? '');
            if (! preg_match('/^[0-9]{1,100}$/D', $call) || ! preg_match('/[1-9]/', $call)) {
                throw new ImportConflict('invalid_call_number');
            }
            if (isset($seen[$call])) {
                throw new ImportConflict('duplicate_call_number');
            }
            $seen[$call] = true;
            $rawCallType = $cells[$callTypeColumn]->rawValue ?? null;
            if (is_string($rawCallType) && CustomerAppDictionary::excludesCallType($rawCallType)) {
                $excluded++;

                continue;
            }
            $identityValue = $identity['kind'] === MasterExportSiteIdentity::KIND
                ? (new MasterExportSiteIdentity)->customerCode($cells[$identity['identity_column']]->rawValue ?? null)
                : trim((string) ($cells[$identity['identity_column']]->rawValue ?? ''));
            if ($identityValue === '' || mb_strlen($identityValue) > 512 || preg_match('/[\x00-\x1f\x7f<>]/', $identityValue)) {
                throw new ImportConflict('invalid_source_site');
            }
            $siteName = (new MasterExportSiteIdentity)->siteName(
                $identity['site_name_column'] === null ? null : ($cells[$identity['site_name_column']]->rawValue ?? null),
            );
            $selection = (new ReviewedWorkbookSelection)->treatment(
                $upload->workbook_hash,
                $sheet->id,
                (int) $rowNumber,
                $call,
                $siteName ?? $identityValue,
                is_string($rawCallType) ? $rawCallType : '',
            );
            if ($selection['excluded']) {
                $excluded++;

                continue;
            }
            $hash = Canonical::hash([$identity['kind'], $identityValue]);
            $rawPlot = $plotColumn !== null ? ($cells[$plotColumn]->rawValue ?? null) : null;
            $sawHierarchySignal = $sawHierarchySignal
                || (is_string($rawPlot) && (preg_match('/\s[-\x{2013}\x{2014}]\s|[-\x{2013}\x{2014}]\s*Plot\s+/iu', $rawPlot) === 1
                    || (new HierarchySuggestion)->forIssue($identityValue, $rawPlot) !== null));
            $sources[$hash] ??= [
                'kind' => $identity['kind'],
                'identity' => $identityValue,
                'customer_code' => $identity['legacy'] ? null : $identityValue,
                'site_name' => $siteName,
                'observed_site_names' => [],
                'warnings' => $identity['legacy'] ? ['LEGACY_SOURCE_IDENTITY'] : [],
                'hash' => $hash,
                'rows' => 0,
                'hierarchy' => ['customer' => null, 'site' => null, 'valid_rows' => 0, 'invalid_rows' => 0, 'conflicting_rows' => 0],
                'hierarchy_issues' => [],
                'hierarchy_variants' => [],
                'plots' => [],
            ];
            if ($plotColumn !== null) {
                $parsed = (new CompositePlotHierarchy)->parse($rawPlot, $identityValue);
                if (! $parsed['valid']) {
                    $sources[$hash]['hierarchy']['invalid_rows']++;
                    $rawHash = Canonical::hash([$rawPlot]);
                    $sources[$hash]['hierarchy_issues'][$rawHash] ??= [
                        'hash' => $rawHash, 'raw' => $rawPlot, 'reason' => $parsed['issue'], 'rows' => [],
                        'suggestion' => (new HierarchySuggestion)->forIssue($identityValue, $rawPlot),
                    ];
                    $sources[$hash]['hierarchy_issues'][$rawHash]['rows'][] = (int) $rowNumber;
                } else {
                    $hierarchy = &$sources[$hash]['hierarchy'];
                    $hierarchy['valid_rows']++;
                    $variantHash = Canonical::hash([MasterSourceResolver::normalizedName($parsed['customer']),
                        MasterSourceResolver::normalizedName($parsed['site'])]);
                    $sources[$hash]['hierarchy_variants'][$variantHash] ??= [
                        'customer' => $parsed['customer'], 'site' => $parsed['site'], 'rows' => [],
                    ];
                    $sources[$hash]['hierarchy_variants'][$variantHash]['rows'][] = (int) $rowNumber;
                    $sources[$hash]['plots'][$parsed['plot']] = true;
                    if ($hierarchy['customer'] === null) {
                        $hierarchy['customer'] = $parsed['customer'];
                        $hierarchy['site'] = $parsed['site'];
                    } elseif (! MasterSourceResolver::sameName($hierarchy['customer'], $parsed['customer'])
                        || ! MasterSourceResolver::sameName($hierarchy['site'], $parsed['site'])) {
                        $hierarchy['conflicting_rows']++;
                    }
                    unset($hierarchy);
                }
            }
            if ($siteName !== null) {
                $sources[$hash]['observed_site_names'][$siteName] = true;
            }
            $sources[$hash]['rows']++;
            $included++;
        }
        if ($seen === [] || $sources === []) {
            throw new ImportConflict('no_source_records');
        }
        $identityHeader = $sheet->cells[$table['header_range']['start_row']][$identity['identity_column']]->rawValue ?? null;
        $compositeMode = ! $identity['legacy'] && ($sawHierarchySignal
            || is_string($identityHeader) && trim($identityHeader) === 'Customer Number');
        ksort($sources, SORT_STRING);
        foreach ($sources as &$source) {
            $source['hierarchy_mode'] = $compositeMode ? 'COMPOSITE' : 'LEGACY_FLAT';
            if (! $compositeMode) {
                $source['hierarchy'] = null;
                $source['hierarchy_issues'] = [];
                $source['hierarchy_variants'] = [];
                $source['plots'] = [];
            } else {
                $source['hierarchy_issues'] = array_values($source['hierarchy_issues']);
                $source['hierarchy_variants'] = array_values($source['hierarchy_variants']);
                $source['plots'] = array_map('strval', array_keys($source['plots']));
                sort($source['plots'], SORT_NATURAL);
            }
            $source['observed_site_names'] = array_keys($source['observed_site_names']);
            sort($source['observed_site_names'], SORT_NATURAL | SORT_FLAG_CASE);
            $source['site_name'] = $source['observed_site_names'][0] ?? $source['site_name'];
            if (count($source['observed_site_names']) > 1) {
                $source['warnings'][] = 'SOURCE_SITE_NAME_VARIATION';
            }
            if ($compositeMode && $source['hierarchy']['invalid_rows'] > 0) {
                $source['warnings'][] = 'SOURCE_HIERARCHY_INVALID';
            }
            if ($compositeMode && $source['hierarchy']['conflicting_rows'] > 0) {
                $source['warnings'][] = 'SOURCE_HIERARCHY_CONFLICT';
            }
        }
        unset($source);

        return [
            'schema' => 'customerapp.wald-pilot-discovery.v5',
            'knowledge_policy' => KnowledgeIdentity::POLICY,
            'resolver_version' => MasterSourceResolver::VERSION,
            'analysis_hash' => $data['analysis_hash'],
            'requires_confirmation' => in_array('no_clear_header', $table['warnings'], true),
            'sheet' => $sheet->id,
            'header' => [$table['header_range']['start_row'], $table['header_range']['end_row']],
            'logical_table' => $table['canonical_reference'] ?? $table['range']['address'],
            'sources' => array_values($sources),
            'source_count' => count($sources),
            'record_count' => count($seen),
            'included_count' => $included,
            'excluded_count' => $excluded,
        ];
    }

    private function single(array $roles, string $role, string $error): int
    {
        $columns = array_keys($roles[$role] ?? []);
        if (count($columns) !== 1) {
            throw new ImportConflict($error);
        }

        return (int) $columns[0];
    }
}
