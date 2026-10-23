<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

/** Structural discovery only; the controlled dictionary remains the sole meaning authority. */
final class PilotWorkbookDiscovery
{
    public const MAX_PARENT_ROWS = 10000;

    public const SCHEMA = 'customerapp.wald-pilot-discovery.v9';

    public function inspect(object $upload, array $restoredRows = []): array
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
        $groupedProfile = new GroupedPlotObservations;
        $groupedDirect = $groupedProfile->matches($sheet, $table);
        $allowedWarnings = $groupedDirect ? ['no_clear_header', 'ambiguous_header_candidates'] : ['no_clear_header'];
        if (array_diff($table['warnings'], $allowedWarnings) !== [] || $sheet->visibility !== 'visible'
            || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
            throw new ImportConflict('unsafe_or_unsupported_structure');
        }
        $groupedRows = $groupedDirect ? $groupedProfile->classify($sheet, $table) : [];

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
        $rowCount = 0;
        $ignoredRows = [];
        $reviewRows = [];
        $sawHierarchySignal = false;
        $dataStart = (int) ($table['data_start_row'] ?? ($table['header_range']['end_row'] + 1));
        $dataEnd = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataEnd
                || ! array_filter($cells, fn ($cell): bool => $cell->rawValue !== null && $cell->rawValue !== '')) {
                continue;
            }
            if (++$rowCount > ($groupedDirect ? WorkbookAnalysisCapacity::DATA_ROWS : self::MAX_PARENT_ROWS)) {
                throw new ImportConflict('pilot_workbook_row_limit_exceeded');
            }
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    throw new ImportConflict('unsafe_source_cell');
                }
            }
            $groupedRow = $groupedRows[(int) $rowNumber] ?? null;
            if ($groupedDirect && $groupedRow === null) {
                throw new ImportConflict('grouped_row_classification_missing');
            }
            if ($groupedDirect && $groupedRow['excluded']) {
                $excluded++;

                continue;
            }
            $groupedIssue = $groupedRow['issue'] ?? null;
            $rawCall = $groupedDirect ? $groupedRow['call_reference'] : ($cells[$callColumn]->rawValue ?? null);
            $call = is_string($rawCall) || is_int($rawCall) ? trim((string) $rawCall) : '';
            $rawCallType = $groupedDirect ? $groupedRow['call_type'] : ($cells[$callTypeColumn]->rawValue ?? null);
            $hasCallType = is_scalar($rawCallType) && trim((string) $rawCallType) !== '';
            if ($call === '') {
                if (! $groupedDirect && ($hasCallType || ($rawCall !== null && (! is_string($rawCall) || trim($rawCall) !== '')))) {
                    throw new ImportConflict('invalid_call_number');
                }
            } else {
                if ((! is_string($rawCall) && ! is_int($rawCall)) || mb_strlen($call) > 100
                    || preg_match('/[\x00-\x1f\x7f]/', $call)) {
                    throw new ImportConflict('invalid_call_number');
                }
                if (isset($seen[$call])) {
                    if (! $groupedDirect) {
                        throw new ImportConflict('duplicate_call_number');
                    }
                    $groupedIssue ??= 'GROUPED_DUPLICATE_CALL_NUMBER';
                } else {
                    $seen[$call] = true;
                }
            }
            $rawIdentity = $groupedDirect
                ? $groupedRow['source_customer_code']
                : ($cells[$identity['identity_column']]->rawValue ?? null);
            if ($identity['kind'] === MasterExportSiteIdentity::KIND
                && (is_string($rawIdentity) || is_int($rawIdentity))
                && CustomerAppDictionary::excludesCustomerCode(trim((string) $rawIdentity))
                && ! isset($restoredRows[(int) $rowNumber])) {
                $excluded++;
                $ignoredRows[] = $this->ignoredRow($rowNumber, $call, $cells, $identity, $plotColumn, $rawCallType,
                    'CUSTOMER_CODE', $groupedDirect ? $rawIdentity : null);

                continue;
            }
            if (is_string($rawCallType) && CustomerAppDictionary::excludesCallType($rawCallType)) {
                $excluded++;

                continue;
            }
            if ($identity['kind'] === MasterExportSiteIdentity::KIND
                && (! is_string($rawIdentity) && ! is_int($rawIdentity) || trim((string) $rawIdentity) === '')) {
                $reviewRows[] = $this->reviewRow($rowNumber, $call, null, null,
                    $groupedDirect ? $groupedRow['plot_reference']
                        : ($plotColumn === null ? null : ($cells[$plotColumn]->rawValue ?? null)), $rawCallType,
                    null, ['valid' => false, 'issue' => $groupedDirect && $groupedIssue !== null
                        ? $groupedIssue : 'CUSTOMER_CODE_MISSING']);

                continue;
            }
            $identityValue = $groupedDirect
                ? trim((string) $rawIdentity)
                : ($identity['kind'] === MasterExportSiteIdentity::KIND
                    ? (new MasterExportSiteIdentity)->customerCode($rawIdentity)
                    : trim((string) $rawIdentity));
            if ($identityValue === '' || mb_strlen($identityValue) > 512 || preg_match('/[\x00-\x1f\x7f<>]/', $identityValue)) {
                throw new ImportConflict('invalid_source_site');
            }
            $siteName = $groupedDirect
                ? (new MasterExportSiteIdentity)->siteName($groupedRow['master_site_name'] ?? null)
                : (new MasterExportSiteIdentity)->siteName(
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
            $rawPlot = $groupedDirect
                ? $groupedRow['plot_reference']
                : ($plotColumn !== null ? ($cells[$plotColumn]->rawValue ?? null) : null);
            $parsed = $plotColumn !== null
                ? (new CompositePlotHierarchy)->parse($rawPlot, $identityValue)
                : ['valid' => false, 'issue' => 'PLOT_COLUMN_MISSING'];
            $reviewRows[] = $this->reviewRow($rowNumber, $call, $identityValue, $siteName,
                $rawPlot, $rawCallType, $hash, $parsed);
            if ($groupedDirect) {
                $reviewRows[array_key_last($reviewRows)]['issue'] ??= $groupedIssue;
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
                'calloff_rows' => 0,
                'activity_ambiguous_rows' => 0,
                'inventory_rows' => 0,
                'recognized_calloff_rows' => 0,
                'unknown_call_type_rows' => 0,
                'blank_type_call_rows' => 0,
                'hierarchy' => ['customer' => null, 'site' => null, 'valid_rows' => 0, 'invalid_rows' => 0, 'conflicting_rows' => 0],
                'hierarchy_issues' => [],
                'hierarchy_variants' => [],
                'plots' => [],
                'calloff_plots' => [],
            ];
            if ($groupedDirect) {
                $sources[$hash]['source_profile'] = GroupedPlotObservations::NATIVE_PROFILE;
            }
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
                    if ($groupedDirect && $call !== '') {
                        $sources[$hash]['calloff_plots'][$parsed['plot']] = true;
                    }
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
            if ($groupedDirect && $groupedIssue !== null) {
                $sources[$hash]['hierarchy']['invalid_rows']++;
                if (in_array($groupedIssue, ['GROUPED_CALL_NUMBER_MISSING', 'GROUPED_INVALID_CALL_NUMBER'], true)) {
                    $sources[$hash]['activity_ambiguous_rows']++;
                }
                $groupedIssueHash = Canonical::hash(['grouped-row', $groupedIssue, $rowNumber]);
                $sources[$hash]['hierarchy_issues'][$groupedIssueHash] = [
                    'hash' => $groupedIssueHash,
                    'raw' => $rawPlot,
                    'raw_call' => $groupedRow['provenance']['raw_call'] ?? null,
                    'raw_type' => $groupedRow['provenance']['raw_type'] ?? null,
                    'reason' => $groupedIssue,
                    'rows' => [(int) $rowNumber],
                    'suggestion' => null,
                ];
            }
            if ($siteName !== null) {
                $sources[$hash]['observed_site_names'][$siteName] = true;
            }
            $sources[$hash]['rows']++;
            if ($call === '') {
                $sources[$hash]['inventory_rows']++;
            } else {
                $sources[$hash]['calloff_rows']++;
                if ($groupedDirect) {
                    $callCategory = PlotRegisterCapabilities::category($rawCallType);
                    if ($callCategory === 'RECOGNISED_CALLOFF_ROW') {
                        $sources[$hash]['recognized_calloff_rows']++;
                    } elseif ($callCategory === 'UNKNOWN_NONBLANK_CALLOFF_ROW') {
                        $sources[$hash]['unknown_call_type_rows']++;
                    } elseif ($callCategory === 'BASE_PLOT_ROW') {
                        $sources[$hash]['blank_type_call_rows']++;
                    }
                }
            }
            $included++;
        }
        if ($rowCount === 0 || ($sources === [] && $reviewRows === [])) {
            throw new ImportConflict('no_source_records');
        }
        $identityHeader = $sheet->cells[$table['header_range']['start_row']][$identity['identity_column']]->rawValue ?? null;
        $compositeMode = $groupedDirect || (! $identity['legacy'] && ($sawHierarchySignal
            || is_string($identityHeader) && trim($identityHeader) === 'Customer Number'));
        $inactiveHashes = [];
        $inactiveRows = 0;
        foreach ($sources as $hash => &$source) {
            $provenZeroCall = $groupedDirect
                ? GroupedPlotObservations::isZeroCallSource($source)
                : $source['calloff_rows'] === 0;
            if ($provenZeroCall) {
                $inactiveHashes[$hash] = true;
                $inactiveRows += $source['rows'];
                $source['requires_manual_review'] = true;
                $source['warnings'][] = 'ZERO_CALLOFF_SITE_REQUIRES_REVIEW';
            }
        }
        unset($source);
        ksort($sources, SORT_STRING);
        foreach ($sources as &$source) {
            $source['hierarchy_mode'] = $compositeMode ? 'COMPOSITE' : 'LEGACY_FLAT';
            if (! $compositeMode) {
                $source['hierarchy'] = null;
                $source['hierarchy_issues'] = [];
                $source['hierarchy_variants'] = [];
                $source['plots'] = [];
            } else {
                // Variant row lists are only needed to explain a real within-code conflict.
                // Keeping them for every ordinary row duplicates the plot inventory and can
                // push a valid native RedZebra manifest over Canonical's bounded payload cap.
                if ($source['hierarchy']['conflicting_rows'] === 0) {
                    $source['hierarchy_variants'] = [];
                }
                $source['hierarchy_issues'] = array_values($source['hierarchy_issues']);
                $source['hierarchy_variants'] = array_values($source['hierarchy_variants']);
                $source['plots'] = array_map('strval', array_keys($source['plots']));
                sort($source['plots'], SORT_NATURAL);
            }
            if (($source['source_profile'] ?? null) === GroupedPlotObservations::NATIVE_PROFILE) {
                $allPlots = array_fill_keys($source['plots'], true);
                $calloffPlots = $source['calloff_plots'];
                $source['plot_count'] = count($allPlots);
                $source['plots_with_calloffs'] = count(array_intersect_key($allPlots, $calloffPlots));
                $source['inventory_only_plots'] = count(array_diff_key($allPlots, $calloffPlots));
                $source['inventory_rows'] = $source['inventory_only_plots'];
                if ($source['unknown_call_type_rows'] > 0 || $source['blank_type_call_rows'] > 0) {
                    $source['warnings'][] = 'UNRESOLVED_CALLOFF_ROWS_PRESENT';
                }
            }
            unset($source['calloff_plots']);
            $source['observed_site_names'] = array_keys($source['observed_site_names']);
            sort($source['observed_site_names'], SORT_NATURAL | SORT_FLAG_CASE);
            $source['site_name'] = $source['observed_site_names'][0] ?? $source['site_name'];
            if (count($source['observed_site_names']) > 1) {
                $source['warnings'][] = 'SOURCE_SITE_NAME_VARIATION';
            }
            $source['requires_manual_review'] = $source['calloff_rows'] === 0
                || ($compositeMode && $source['hierarchy']['invalid_rows'] > 0)
                || ($source['source_profile'] ?? null) === GroupedPlotObservations::NATIVE_PROFILE
                    && ($source['unknown_call_type_rows'] > 0 || $source['blank_type_call_rows'] > 0);
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
            'source_profile' => $groupedDirect ? GroupedPlotObservations::NATIVE_PROFILE : 'flat-v1',
            'knowledge_policy' => KnowledgeIdentity::POLICY,
            'resolver_version' => MasterSourceResolver::VERSION,
            'analysis_hash' => $data['analysis_hash'],
            'requires_confirmation' => in_array('no_clear_header', $table['warnings'], true),
            'sheet' => $sheet->id,
            'header' => [$table['header_range']['start_row'], $table['header_range']['end_row']],
            'logical_table' => $table['canonical_reference'] ?? $table['range']['address'],
            'sources' => array_values($sources),
            'source_count' => count($sources),
            'record_count' => $rowCount,
            'included_count' => $included,
            'excluded_count' => $excluded,
            'inactive_site_count' => count($inactiveHashes),
            'inactive_row_count' => $inactiveRows,
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
            'issue' => $parsed['valid'] ? null : $parsed['issue'],
        ];
    }

    private function ignoredRow(int $rowNumber, string $call, array $cells, array $identity, ?int $plotColumn,
        mixed $rawCallType, string $reason, mixed $resolvedCode = null): array
    {
        $rawCode = $resolvedCode ?? ($cells[$identity['identity_column']]->rawValue ?? null);
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

    private function single(array $roles, string $role, string $error): int
    {
        $columns = array_keys($roles[$role] ?? []);
        if (count($columns) !== 1) {
            throw new ImportConflict($error);
        }

        return (int) $columns[0];
    }
}
