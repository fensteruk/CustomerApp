<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

/** Structural discovery only; the controlled dictionary remains the sole meaning authority. */
final class PilotWorkbookDiscovery
{
    public const MAX_PARENT_ROWS = 5000;

    public const SCHEMA = 'customerapp.wald-pilot-discovery.v10';

    public function inspect(object $upload, array $restoredRows = [], ?array $inspection = null): array
    {
        [, $sheets, $snapshot] = $inspection ?? (new WorkbookStager)->inspect($upload);
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
        $capabilities = PlotRegisterCapabilities::forRoles($roles);
        $callColumn = $this->single($roles, 'call_reference', 'required_call_column_unresolved', $capabilities['plot_register']);
        $callTypeColumn = $this->single($roles, 'call_type', 'required_call_type_column_unresolved', $capabilities['plot_register']);
        $plotColumns = array_keys($roles['plot_reference'] ?? []);
        $plotColumn = count($plotColumns) === 1 ? (int) $plotColumns[0] : null;
        $identity = (new MasterExportSiteIdentity)->columns($roles, $upload->workbook_hash);
        $findingColumns = [];
        foreach ($roles as $role => $candidates) {
            if (count($candidates) === 1) {
                $findingColumns[$role] = (int) array_key_first($candidates);
            }
        }
        $callFindings = (new ScopedCallEvidence)->discover($sheet, $table, $findingColumns);

        $seen = [];
        $physicalRows = 0;
        $sources = [];
        $included = 0;
        $excluded = 0;
        $ignoredRows = [];
        $reviewRows = [];
        $sawHierarchySignal = false;
        $hasUnnumberedBaseRows = false;
        $dataStart = (int) ($table['data_start_row'] ?? ($table['header_range']['end_row'] + 1));
        $dataEnd = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataEnd
                || ! array_filter($cells, fn ($cell): bool => $cell->rawValue !== null && $cell->rawValue !== '')) {
                continue;
            }
            if ($physicalRows++ >= self::MAX_PARENT_ROWS) {
                throw new ImportConflict('pilot_workbook_row_limit_exceeded');
            }
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    throw new ImportConflict('unsafe_source_cell');
                }
            }
            $rawCall = $callColumn === null ? null : ($cells[$callColumn]->rawValue ?? null);
            $emptyCall = $rawCall === null || is_string($rawCall) && trim($rawCall) === '';
            $call = $emptyCall ? '' : (string) $rawCall;
            if (! $emptyCall && ((! is_int($rawCall) && ! is_string($rawCall)) || ! preg_match('/^[0-9]{1,100}$/D', $call) || ! preg_match('/[1-9]/', $call))) {
                throw new ImportConflict('invalid_call_number');
            }
            if ($call !== '') {
                $seen[$call] = true;
            }
            $rawCallType = $callTypeColumn === null ? null : ($cells[$callTypeColumn]->rawValue ?? null);
            $rawIdentity = $cells[$identity['identity_column']]->rawValue ?? null;
            if ($identity['kind'] === MasterExportSiteIdentity::KIND
                && (is_string($rawIdentity) || is_int($rawIdentity))
                && CustomerAppDictionary::excludesCustomerCode(trim((string) $rawIdentity))
                && ! isset($restoredRows[(int) $rowNumber])) {
                $excluded++;
                $ignoredRows[] = $this->ignoredRow($rowNumber, $call, $cells, $identity, $plotColumn, $rawCallType, 'CUSTOMER_CODE');

                continue;
            }
            if (is_string($rawCallType) && CustomerAppDictionary::excludesCallType($rawCallType)) {
                $excluded++;

                continue;
            }
            if ($identity['kind'] === MasterExportSiteIdentity::KIND
                && (! is_string($rawIdentity) && ! is_int($rawIdentity) || trim((string) $rawIdentity) === '')) {
                $reviewRows[] = $this->reviewRow($rowNumber, $call, null, null,
                    $plotColumn === null ? null : ($cells[$plotColumn]->rawValue ?? null), $rawCallType,
                    null, ['valid' => false, 'issue' => 'CUSTOMER_CODE_MISSING']);

                continue;
            }
            $identityValue = $identity['kind'] === MasterExportSiteIdentity::KIND
                ? (new MasterExportSiteIdentity)->customerCode($rawIdentity)
                : trim((string) $rawIdentity);
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
            $hasUnnumberedBaseRows = $hasUnnumberedBaseRows || $call === '' && PlotRegisterCapabilities::category($rawCallType) === 'BASE_PLOT_ROW';
            $hash = Canonical::hash([$identity['kind'], $identityValue]);
            $rawPlot = $plotColumn !== null ? ($cells[$plotColumn]->rawValue ?? null) : null;
            $parsed = $plotColumn !== null
                ? (new CompositePlotHierarchy)->parse($rawPlot, $identityValue)
                : ['valid' => false, 'issue' => 'PLOT_COLUMN_MISSING'];
            $reviewRows[] = $this->reviewRow($rowNumber, $call, $identityValue, $siteName,
                $rawPlot, $rawCallType, $hash, $parsed);
            if (isset($callFindings[$call]) && $reviewRows[array_key_last($reviewRows)]['issue'] === null) {
                $reviewRows[array_key_last($reviewRows)]['issue'] = 'DUPLICATE_CALL_NUMBER';
            }
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
        if ($physicalRows === 0 || ($sources === [] && $reviewRows === [])) {
            throw new ImportConflict('no_source_records');
        }
        $identityHeader = $sheet->cells[$table['header_range']['start_row']][$identity['identity_column']]->rawValue ?? null;
        $plotHeader = $plotColumn === null ? null : ($sheet->cells[$table['header_range']['start_row']][$plotColumn]->rawValue ?? null);
        $compositeMode = ! $identity['legacy'] && (in_array($table['source_profile'] ?? null, [RedZebraExportProfile::ALTERNATE, RedZebraExportProfile::PLOT_REGISTER], true) || $sawHierarchySignal
            || is_string($plotHeader) && strcasecmp(trim($plotHeader), 'Plot Ref') === 0
                && ($callColumn === null || $callTypeColumn === null || $hasUnnumberedBaseRows)
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
            'schema' => self::SCHEMA,
            'knowledge_policy' => KnowledgeIdentity::POLICY,
            'resolver_version' => MasterSourceResolver::VERSION,
            'analysis_hash' => $data['analysis_hash'],
            'requires_confirmation' => in_array('no_clear_header', $table['warnings'], true),
            'sheet' => $sheet->id,
            'header' => [$table['header_range']['start_row'], $table['header_range']['end_row']],
            'logical_table' => $table['canonical_reference'] ?? $table['range']['address'],
            'source_profile' => $table['source_profile'] ?? null,
            'capabilities' => $capabilities,
            'call_findings' => array_values($callFindings),
            'row_summary' => [...PlotRegisterCapabilities::summary($reviewRows),
                'repeated_call_groups' => count($callFindings),
                'call_blocker_rows' => count(array_filter($reviewRows, fn ($row) => isset($callFindings[$row['call_no']])))],
            'sources' => array_values($sources),
            'source_count' => count($sources),
            'record_count' => $physicalRows,
            'included_count' => $included,
            'excluded_count' => $excluded,
            'dictionary_excluded_count' => $excluded,
            'office_excluded_count' => 0,
            'unclassified_count' => count(array_filter($reviewRows,
                fn (array $row): bool => $row['source_identity_hash'] === null)),
            'ignored_rows' => $ignoredRows,
            'review_rows' => $reviewRows,
        ];
    }

    private function reviewRow(int $rowNumber, string $call, ?string $code, ?string $site,
        mixed $rawPlot, mixed $rawCallType, ?string $sourceHash, array $parsed): array
    {
        return [
            'row_number' => $rowNumber,
            'source_identity_hash' => $sourceHash,
            'customer_code' => $code,
            'call_no' => $call,
            'call_type' => $rawCallType === null ? null : mb_substr(trim((string) $rawCallType), 0, 100),
            'source_site_name' => $site,
            'raw_plot_ref' => $rawPlot === null ? null : (string) $rawPlot,
            'parsed_customer' => $parsed['valid'] ? $parsed['customer'] : null,
            'parsed_site' => $parsed['valid'] ? $parsed['site'] : null,
            'parsed_plot' => $parsed['valid'] ? $parsed['plot'] : null,
            'issue' => $parsed['valid']
                ? match (true) {
                    PlotRegisterCapabilities::category($rawCallType) === 'UNKNOWN_NONBLANK_CALLOFF_ROW' => 'UNKNOWN_CALL_TYPE',
                    $call === '' && PlotRegisterCapabilities::category($rawCallType) === 'RECOGNISED_CALLOFF_ROW' => 'MISSING_CALLOFF_REFERENCE',
                    default => null,
                }
                : $parsed['issue'],
        ];
    }

    private function ignoredRow(int $rowNumber, string $call, array $cells, array $identity, ?int $plotColumn, mixed $rawCallType, string $reason): array
    {
        $rawCode = $cells[$identity['identity_column']]->rawValue ?? null;
        $rawPlot = $plotColumn === null ? null : ($cells[$plotColumn]->rawValue ?? null);

        return [
            'row_number' => $rowNumber,
            'call_no' => $call,
            'customer_code' => $rawCode === null ? null : mb_substr(trim((string) $rawCode), 0, 512),
            'call_type' => $rawCallType === null ? null : mb_substr(trim((string) $rawCallType), 0, 100),
            'plot_ref' => $rawPlot === null ? null : mb_substr((string) $rawPlot, 0, 2000),
            'reason' => $reason,
        ];
    }

    private function single(array $roles, string $role, string $error, bool $optional = false): ?int
    {
        $columns = array_keys($roles[$role] ?? []);
        if ($optional && $columns === []) {
            return null;
        }
        if (count($columns) !== 1) {
            throw new ImportConflict($error);
        }

        return (int) $columns[0];
    }
}
