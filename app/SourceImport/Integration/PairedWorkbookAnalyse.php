<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Readers\XlsWorkbookSource;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\SourceImport\Semantics\Dictionary\Quantity;
use App\Wald\Contracts\SourceRange;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\WorkbookSourceFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/** Read-only paired master-inventory/call-off reconciliation. It has no Apply path. */
final class PairedWorkbookAnalyse
{
    private const SCHEMA = 'customerapp.wald-vnext-paired-analyse.v1';

    public function analyse(User $actor, UploadedFile $masterFile, UploadedFile $callFile): array
    {
        (new PairedWorkbookAnalysePolicy)->authorize($actor);
        $started = hrtime(true);
        $storage = new PrivateWorkbookStorage;
        $artifacts = [];
        try {
            $artifacts['master'] = $storage->storeForPairedAnalysis($masterFile);
            $artifacts['call_off'] = $storage->storeForPairedAnalysis($callFile);
            $masterInspection = $this->inspectPairedWorkbook($artifacts['master'], 'master');
            [$masterRows, $masterProducts, $masterMeta] = $this->master($masterInspection, $artifacts['master']);
            unset($masterInspection);
            $callInspection = $this->inspectPairedWorkbook($artifacts['call_off'], 'call_off');
            [$callRows, $callProducts, $callMeta] = $this->callOff($callInspection, $artifacts['call_off']);
            unset($callInspection);

            $result = $this->reconcile($masterRows, $callRows, $masterProducts, $callProducts, $masterMeta, $callMeta);
            WorkbookAnalysisCapacity::guardDuration($started);

            return $result;
        } catch (AnalysisProblem $exception) {
            throw $exception;
        } finally {
            foreach ($artifacts as $artifact) {
                try {
                    $storage->discardUnregistered($artifact['storage_key']);
                } catch (\Throwable $cleanupError) {
                    Log::warning('Paired workbook temporary cleanup failed.', [
                        'storage_key' => $artifact['storage_key'], 'exception' => $cleanupError::class,
                    ]);
                }
            }
        }
    }

    private function master(array $inspection, array $artifact): array
    {
        [$sheet, $table] = $inspection;
        $grouped = new GroupedPlotObservations;
        if (! $grouped->matches($sheet, $table)) {
            throw new ImportConflict('master_register_profile_not_recognised');
        }
        // Paired identity comes from the approved AA code/plot data when present;
        // malformed descriptive B text remains evidence for the paired review.
        $logical = $grouped->classify($sheet, $table, allowMalformedParentHierarchy: true);
        $plots = [];
        $facts = [];
        $childrenByParent = [];
        foreach ($logical as $row) {
            if ($row['kind'] === 'CHILD_DETAIL' && $row['provenance']['parent_row'] !== null) {
                $childrenByParent[$row['provenance']['parent_row']][] = $row;
            }
        }
        $header = $sheet->cells[$table['header_range']['start_row']];
        $productColumns = $this->productColumns($header, true);
        foreach ($logical as $rowNumber => $row) {
            if ($row['excluded']) {
                continue;
            }
            if ($row['kind'] !== 'PARENT_PLOT') {
                continue;
            }
            $identityRow = null;
            if (is_string($row['provenance']['identity_cell'] ?? null)
                && preg_match('/^AA([1-9][0-9]*)$/D', $row['provenance']['identity_cell'], $match)) {
                $identityRow = (int) $match[1];
            }
            $identity = $this->masterSitePlot(
                $identityRow !== null ? ($sheet->cells[$identityRow][27]->rawValue ?? null) : null,
                $identityRow !== null ? ($sheet->cells[$identityRow][26]->rawValue ?? null) : null,
            );
            $parsed = $this->masterHierarchy($row['plot_reference'], $identity['valid'] ? $identity['site_reference'] : null);
            if (! $identity['valid']) {
                $plots[] = ['site_reference' => $identity['valid'] ? $identity['site_reference'] : null, 'plot' => null,
                    'customer' => $parsed['customer'] ?? null, 'site' => $parsed['site'] ?? null,
                    'master_site_name' => $row['master_site_name'] ?? null,
                    'raw_plot' => $row['plot_reference'] ?? null, 'valid' => false,
                    'issue' => $identity['issue'],
                    'row' => $rowNumber, 'parent_row' => $rowNumber, 'provenance' => $row['provenance']];
            } elseif ($parsed['valid'] && isset($parsed['plot']) && $parsed['plot'] !== $identity['plot']) {
                $plots[] = ['site_reference' => $identity['site_reference'], 'plot' => null,
                    'customer' => $parsed['customer'], 'site' => $parsed['site'], 'raw_plot' => $row['plot_reference'],
                    'master_site_name' => $row['master_site_name'] ?? null,
                    'valid' => false, 'issue' => 'MASTER_HIERARCHY_PLOT_MISMATCH', 'row' => $rowNumber,
                    'parent_row' => $rowNumber, 'provenance' => $row['provenance']];

            } else {
                // AA is the approved source of the exact site code and plot
                // reference. Column B remains descriptive evidence; an
                // unparseable display label cannot erase a valid AA identity.
                $plots[] = ['site_reference' => $identity['site_reference'], 'plot' => $identity['plot'],
                    'customer' => $parsed['customer'] ?? null, 'site' => $parsed['site'] ?? null, 'raw_plot' => $row['plot_reference'],
                    'master_site_name' => $row['master_site_name'] ?? null,
                    'valid' => true, 'issue' => null, 'row' => $rowNumber,
                    'parent_row' => $rowNumber, 'provenance' => $row['provenance']];
            }

            foreach ($childrenByParent[$rowNumber] ?? [] as $child) {
                $childRow = $child['provenance']['row'];
                $childIdentity = $this->masterSitePlot($sheet->cells[$childRow][27]->rawValue ?? null,
                    $sheet->cells[$childRow][26]->rawValue ?? null);
                if (! $childIdentity['valid'] || ! $identity['valid']
                    || $childIdentity['site_reference'] !== $identity['site_reference']
                    || $childIdentity['plot'] !== $identity['plot'] || $child['issue'] !== null) {
                    $plots[] = ['site_reference' => $identity['valid'] ? $identity['site_reference'] : null, 'plot' => null,
                        'customer' => $parsed['customer'] ?? null, 'site' => $parsed['site'] ?? null,
                        'master_site_name' => $row['master_site_name'] ?? null,
                        'raw_plot' => $row['plot_reference'], 'valid' => false,
                        'issue' => $child['issue'] ?? 'MASTER_PLOT_IDENTITY_MISMATCH',
                        'row' => $childRow, 'parent_row' => $rowNumber, 'provenance' => $child['provenance']];

                    continue;
                }
                $facts[] = ['site_reference' => $identity['site_reference'], 'plot' => $identity['plot'],
                    'call' => $child['call_reference'],
                    'products' => $this->quantities($sheet->cells[$childRow] ?? [], $productColumns),
                    'source' => 'master', 'row' => $childRow, 'call_cell' => $child['provenance']['call_cell'] ?? null];
            }
        }
        $meta = $this->metadata($sheet, $table, $artifact, 'master_register');

        return [$plots, $facts, $meta + ['product_columns' => $productColumns]];
    }

    private function callOff(array $inspection, array $artifact): array
    {
        [$sheet, $table] = $inspection;
        $header = $sheet->cells[$table['header_range']['start_row']];
        $columns = $this->requiredColumns($header, ['Call No.', 'Customer Number', 'Site Name', 'Plot Ref', 'Call Type']);
        $productColumns = $this->productColumns($header);
        $rows = [];
        $facts = [];
        $start = (int) ($table['data_start_row'] ?? ($table['header_range']['end_row'] + 1));
        $end = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        for ($number = $start; $number <= $end; $number++) {
            $cells = $sheet->cells[$number] ?? [];
            if (! array_filter($cells, fn ($cell) => $cell->hasContent())) {
                continue;
            }
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    throw new ImportConflict('unsafe_source_cell');
                }
            }
            $rawCode = $cells[$columns['Customer Number']]->rawValue ?? null;
            $code = is_string($rawCode) || is_int($rawCode) ? trim((string) $rawCode) : '';
            if ($code !== '' && CustomerAppDictionary::excludesCustomerCode($code)) {
                continue;
            }
            $rawCall = $cells[$columns['Call No.']]->rawValue ?? null;
            $callHasSupportedType = is_int($rawCall) || is_string($rawCall);
            $call = is_int($rawCall) || is_string($rawCall) ? trim((string) $rawCall) : '';
            $validCall = $callHasSupportedType && $call !== '' && mb_strlen($call) <= 100 && ! preg_match('/[\x00-\x1f\x7f]/', $call);
            $rawPlot = $cells[$columns['Plot Ref']]->rawValue ?? null;
            $parsed = (new CompositePlotHierarchy)->parse($rawPlot, $code !== '' ? $code : null);
            $rawSiteName = $cells[$columns['Site Name']]->rawValue ?? null;
            $rows[] = ['code' => $code, 'call' => $validCall ? $call : null, 'valid_call' => (bool) $validCall,
                'customer' => $parsed['customer'] ?? null, 'site' => $parsed['site'] ?? null,
                'plot' => $parsed['valid'] ? $parsed['plot'] : null, 'raw_plot' => $rawPlot,
                'call_type' => $cells[$columns['Call Type']]->rawValue ?? null, 'source_site_name' => $rawSiteName,
                'valid_hierarchy' => $parsed['valid'],
                'issue' => ! $validCall
                    ? ($call === '' && ($rawCall === null || is_string($rawCall) && trim($rawCall) === '') ? null : 'INVALID_CALL_NUMBER')
                    : ($parsed['valid'] ? null : $parsed['issue']),
                'row' => $number, 'plot_cell' => $cells[$columns['Plot Ref']]->coordinate ?? 'D'.$number];
            if ($code !== '' && $validCall && $parsed['valid']) {
                $facts[] = ['code' => $code, 'plot' => $parsed['plot'], 'call' => $validCall ? $call : '',
                    'products' => $this->quantities($cells, $productColumns), 'source' => 'call_off', 'row' => $number];
            }
        }
        $missingCodeRows = count(array_filter($rows, fn ($row): bool => $row['code'] === ''));

        return [$rows, $facts, $this->metadata($sheet, $table, $artifact, 'call_off_report')
            + ['product_columns' => $productColumns, 'missing_customer_code_rows' => $missingCodeRows]];
    }

    private function inspectPairedWorkbook(array $artifact, string $kind): array
    {
        $storage = new PrivateWorkbookStorage;
        $path = $storage->path((object) $artifact);
        $budget = new AnalysisBudget(['rows' => WorkbookAnalysisCapacity::DATA_ROWS + 1,
            'seconds' => WorkbookAnalysisCapacity::SECONDS]);
        $source = $artifact['format'] === 'xls' ? new XlsWorkbookSource($path, $budget)
            : (new WorkbookSourceFactory)->open($path, $artifact['format'], $budget);
        try {
            $sheets = iterator_to_array($source->sheets(), false);
        } finally {
            $source->close();
        }
        if (count($sheets) !== 1) {
            throw new ImportConflict('paired_single_sheet_required');
        }
        $sheet = $sheets[0];
        if ($sheet->visibility !== 'visible' || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
            throw new ImportConflict('unsafe_or_unsupported_structure');
        }
        $header = $sheet->cells[1] ?? [];
        if ($kind === 'call_off') {
            $this->requiredColumns($header, ['Call No.', 'Customer Number', 'Site Name', 'Plot Ref', 'Call Type']);
        } else {
            foreach ([1 => 'Call No.', 2 => 'Site Name', 3 => 'Plot Ref', 4 => 'Call Type', 27 => 'Customer Number', 28 => 'Site Number'] as $column => $label) {
                if (trim($header[$column]->rawValue ?? '') !== $label) {
                    throw new ImportConflict('master_register_profile_not_recognised');
                }
            }
        }
        $lastRow = $sheet->cells === [] ? 1 : max(array_keys($sheet->cells));
        $lastColumn = 1;
        foreach ($sheet->cells as $cells) {
            if ($cells !== []) {
                $lastColumn = max($lastColumn, max(array_keys($cells)));
            }
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    throw new ImportConflict('unsafe_source_cell');
                }
            }
        }
        $table = ['header_range' => ['start_row' => 1, 'end_row' => 1], 'data_start_row' => 2,
            'data_end_row' => $lastRow, 'range' => ['end_row' => $lastRow],
            'canonical_reference' => 'A1:'.SourceRange::columnLetters($lastColumn).$lastRow,
            'source_profile' => 'paired_'.$kind.'_exact_header_v1'];

        return [$sheet, $table];
    }

    private function reconcile(array $masterRows, array $callRows, array $masterFacts, array $callFacts, array $masterMeta, array $callMeta): array
    {
        $masterSites = [];
        foreach ($masterRows as $row) {
            $siteReference = $row['site_reference'] ?? null;
            if (! is_string($siteReference) || $siteReference === '') {
                continue;
            }
            $masterSites[$siteReference] ??= ['plots' => [], 'hierarchies' => [], 'issues' => []];
            $siteName = $row['master_site_name'] ?? null;
            if (is_string($siteName) && trim($siteName) !== '') {
                $siteNameKey = MasterSourceResolver::normalizedName($siteName);
                $masterSites[$siteReference]['site_names'][$siteNameKey] ??= trim($siteName);
            }
            $hierarchy = $this->hierarchyKey($row['customer'], $row['site']);
            if (is_string($row['customer']) && $row['customer'] !== '' && is_string($row['site']) && $row['site'] !== '') {
                $masterSites[$siteReference]['hierarchies'][$hierarchy] ??= ['customer' => $row['customer'], 'site' => $row['site']];
            }
            if (! $row['valid'] || ! $row['plot']) {
                $masterSites[$siteReference]['issues']['master'][] = ['row' => $row['row'], 'issue' => $row['issue'] ?? 'INVALID_MASTER_PLOT'];

                continue;
            }
            if (isset($masterSites[$siteReference]['plots'][$row['plot']])) {
                $masterSites[$siteReference]['issues']['duplicates'][] = ['plot' => $row['plot'], 'rows' => [$masterSites[$siteReference]['plots'][$row['plot']]['row'], $row['row']]];
            } else {
                $masterSites[$siteReference]['plots'][$row['plot']] = $row;
            }
        }

        foreach ($masterSites as $customerNumber => $data) {
            if (count($data['hierarchies']) > 1) {
                $masterSites[$customerNumber]['issues']['master_hierarchy_conflict'] = array_values($data['hierarchies']);
            }
        }

        $callsByCode = [];
        $callsByPlot = [];
        $hierarchiesByCode = [];
        $siteNamesByCode = [];
        $callIssues = [];
        $seenCalls = [];
        foreach ($callRows as $row) {
            $code = $row['code'];
            if (! is_string($code) || $code === '') {
                continue;
            }
            if ($row['valid_call'] && ! $row['valid_hierarchy']) {
                $callIssues[$code]['invalid_hierarchy'][] = ['row' => $row['row'], 'issue' => $row['issue'] ?? 'INVALID_CALL_HIERARCHY'];

                continue;
            }
            if (! $row['valid_hierarchy']) {
                continue;
            }
            $key = $this->hierarchyKey($row['customer'], $row['site']);
            $hierarchiesByCode[$code][$key] ??= ['customer' => $row['customer'], 'site' => $row['site']];
            $siteName = $row['source_site_name'] ?? null;
            if (is_string($siteName) && trim($siteName) !== '') {
                $siteNamesByCode[$code][MasterSourceResolver::normalizedName($siteName)] ??= trim($siteName);
            } elseif ($row['valid_call']) {
                $callIssues[$code]['missing_site_name'][] = ['row' => $row['row']];
            }
            if (! $row['valid_call']) {
                continue;
            }
            $callIdentity = $row['call'];
            if (isset($seenCalls[$callIdentity])) {
                $duplicate = ['call' => $row['call'], 'rows' => [$seenCalls[$callIdentity]['row'], $row['row']]];
                $callIssues[$code]['duplicate_calls'][] = $duplicate;
                $callIssues[$seenCalls[$callIdentity]['code']]['duplicate_calls'][] = $duplicate;
            } else {
                $seenCalls[$callIdentity] = ['code' => $code, 'row' => $row['row']];
            }
            $callsByCode[$code][] = $row;
            $callsByPlot[$code][$row['plot']][] = $row;
        }
        foreach ($hierarchiesByCode as $code => $variants) {
            if (count($variants) > 1) {
                $callIssues[$code]['hierarchy_conflict'] = array_values($variants);
            }
        }
        // CustomerCode is the cross-workbook identity. Variations in the
        // descriptive Site Name column are retained for Office context but do
        // not override an exact code and validated Plot Ref hierarchy.
        $codeToMasterSite = [];
        $allCodes = array_unique([...array_keys($callsByCode), ...array_keys($callIssues)]);
        foreach ($allCodes as $code) {
            $variants = $hierarchiesByCode[$code] ?? [];
            if (isset($masterSites[$code])) {
                $siteReference = $code;
                $masterHierarchyKeys = array_keys($masterSites[$siteReference]['hierarchies']);
                $callHierarchyKey = count($variants) === 1 ? array_key_first($variants) : null;
                if ($callHierarchyKey !== null
                    && (count($masterHierarchyKeys) !== 1 || $masterHierarchyKeys[0] !== $callHierarchyKey)) {
                    $callIssues[$code]['hierarchy_conflict'] = [
                        ...array_values($masterSites[$siteReference]['hierarchies']), ...array_values($variants),
                    ];
                }
                $codeToMasterSite[$code] = $siteReference;
            }
        }
        $codesByMasterSite = [];
        foreach ($codeToMasterSite as $code => $siteReference) {
            $codesByMasterSite[$siteReference][] = $code;
        }
        foreach ($codesByMasterSite as $siteReference => $codes) {
            if (count($codes) > 1) {
                $masterSites[$siteReference]['issues']['multiple_customer_codes_for_site'] = $codes;
                foreach ($codes as $code) {
                    $callIssues[$code]['multiple_customer_codes_for_site'] = $codes;
                }
            }
        }

        $sites = [];
        $includedPlotKeys = [];
        $callOnlyPlots = [];
        $usedCodes = [];
        foreach ($masterSites as $siteId => $masterData) {
            $siteReference = (string) $siteId;
            $codes = $codesByMasterSite[$siteId] ?? [];
            $code = count($codes) === 1 ? $codes[0] : null;
            $calls = [];
            $issues = $masterData['issues'];
            foreach ($codes as $sourceCode) {
                $usedCodes[$sourceCode] = true;
                $calls = [...$calls, ...($callsByCode[$sourceCode] ?? [])];
                $issues += $callIssues[$sourceCode] ?? [];
            }
            // A site cannot qualify if any master plot identity is invalid or duplicated:
            // silently omitting it would make the promised full inventory incomplete.
            $hasIssues = $issues !== [] || count($masterData['plots']) === 0;
            $status = $hasIssues ? 'REVIEW' : ($calls === [] ? 'ZERO_CALLOFFS_EXCLUDED' : 'QUALIFYING');
            $matched = [];
            $unmatched = [];
            if ($status === 'QUALIFYING') {
                foreach ($masterData['plots'] as $plot => $source) {
                    $includedPlotKeys[$siteId."\0".$plot] = true;
                    if (($callsByPlot[$code][$plot] ?? []) !== []) {
                        $matched[$plot] = $callsByPlot[$code][$plot];
                    }
                }
            }
            foreach ($codes as $sourceCode) {
                foreach ($callsByPlot[$sourceCode] ?? [] as $plot => $plotCalls) {
                    if (! isset($masterData['plots'][$plot])) {
                        $unmatched[$plot] = [...($unmatched[$plot] ?? []), ...$plotCalls];
                        $callOnlyPlots[$sourceCode."\0".$plot] = true;
                    }
                }
            }
            $hierarchyVariants = array_values($masterData['hierarchies']);
            $names = count($hierarchyVariants) === 1 ? $hierarchyVariants[0] : [];
            $sites[] = ['customer_code' => $code, 'master_site_reference' => $siteReference,
                'master_source_site_names' => array_values($masterData['site_names'] ?? []),
                'call_off_source_site_names' => array_values(array_unique(array_merge([], ...array_map(
                    fn ($sourceCode) => array_values($siteNamesByCode[$sourceCode] ?? []), $codes ?: [])))),
                'customer' => $names['customer'] ?? null, 'site' => $names['site'] ?? null, 'status' => $status,
                'master_plot_count' => count($masterData['plots']), 'plots_with_calloffs' => count($matched),
                'plots_without_calloffs' => $status === 'QUALIFYING' ? count($masterData['plots']) - count($matched) : 0,
                'call_only_plot_count' => count($unmatched), 'call_count' => count($calls), 'issues' => $issues,
                'hierarchy_variants' => $hierarchyVariants,
                'call_only_plots' => array_map(fn ($plot, $rows): array => ['plot_ref' => (string) $plot, 'call_rows' => array_column($rows, 'row')], array_keys($unmatched), array_values($unmatched)),
                'plots' => $status === 'QUALIFYING' ? array_map(fn ($plot, $source): array => [
                    'plot_ref' => (string) $plot, 'record_type' => isset($matched[$plot]) ? 'SOURCE_PLOT' : 'BASE_PLOT_ROW',
                    'has_calloff' => isset($matched[$plot]), 'master_row' => $source['row'],
                    'call_rows' => array_column($matched[$plot] ?? [], 'row'),
                ], array_keys($masterData['plots']), array_values($masterData['plots'])) : []];
        }

        // The user confirmed the source contract for this workflow: call-off-only
        // site identities are inactive and excluded; they never create inventory.
        foreach ($callsByCode as $code => $calls) {
            if (isset($usedCodes[$code])) {
                continue;
            }
            $variants = array_values($hierarchiesByCode[$code] ?? []);
            $callPlots = $callsByPlot[$code] ?? [];
            foreach (array_keys($callPlots) as $plot) {
                $callOnlyPlots[$code."\0".$plot] = true;
            }
            $issues = $callIssues[$code] ?? [];
            $status = 'REVIEW';
            $sites[] = ['customer_code' => $code, 'master_site_reference' => null,
                'master_source_site_names' => [], 'call_off_source_site_names' => array_values($siteNamesByCode[$code] ?? []),
                'customer' => count($variants) === 1 ? $variants[0]['customer'] : null,
                'site' => count($variants) === 1 ? $variants[0]['site'] : null,
                'status' => $status, 'master_plot_count' => 0,
                'plots_with_calloffs' => 0, 'plots_without_calloffs' => 0,
                'call_only_plot_count' => count($callPlots), 'call_count' => count($calls),
                'issues' => $issues, 'hierarchy_variants' => $variants,
                'call_only_plots' => array_map(fn ($plot, $rows): array => ['plot_ref' => (string) $plot, 'call_rows' => array_column($rows, 'row')], array_keys($callPlots), array_values($callPlots)),
                'plots' => []];
        }
        foreach ($callIssues as $code => $issues) {
            if (isset($usedCodes[$code]) || isset($callsByCode[$code])) {
                continue;
            }
            $invalidRows = $issues['invalid_hierarchy'] ?? [];
            $sites[] = ['customer_code' => $code, 'master_site_reference' => null, 'customer' => null, 'site' => null,
                'master_source_site_names' => [], 'call_off_source_site_names' => array_values($siteNamesByCode[$code] ?? []),
                'status' => 'REVIEW', 'master_plot_count' => 0, 'plots_with_calloffs' => 0,
                'plots_without_calloffs' => 0, 'call_only_plot_count' => 0, 'call_count' => count($invalidRows),
                'issues' => $issues, 'hierarchy_variants' => [], 'call_only_plots' => [], 'plots' => []];
        }

        $qualifying = array_values(array_filter($sites, fn ($site) => $site['status'] === 'QUALIFYING'));
        $excluded = array_values(array_filter($sites, fn ($site) => $site['status'] === 'ZERO_CALLOFFS_EXCLUDED'));
        $review = array_values(array_filter($sites, fn ($site) => $site['status'] === 'REVIEW'));
        $codeByMasterSite = [];
        foreach ($codesByMasterSite as $siteReference => $codes) {
            if (count($codes) === 1) {
                $codeByMasterSite[$siteReference] = $codes[0];
            }
        }
        $masterFacts = array_values(array_filter(array_map(fn ($fact): array => [
            ...$fact, 'code' => $codeByMasterSite[$fact['site_reference']] ?? null,
        ], $masterFacts), fn ($fact): bool => is_string($fact['code'])));
        $qualifyingCodes = array_fill_keys(array_column($qualifying, 'customer_code'), true);
        $relevantFacts = array_filter([...$masterFacts, ...$callFacts], fn ($fact): bool => isset($qualifyingCodes[$fact['code']]));
        $productConflicts = $this->productConflicts($relevantFacts);
        $examples = [];
        foreach ($qualifying as $site) {
            foreach ($site['plots'] as $plot) {
                if ($plot['has_calloff']) {
                    $examples[] = ['customer_code' => $site['customer_code'], 'plot_ref' => $plot['plot_ref'],
                        'master_row' => $plot['master_row'], 'call_rows' => $plot['call_rows']];
                    if (count($examples) === 5) {
                        break 2;
                    }
                }
            }
        }
        $content = ['schema' => self::SCHEMA, 'mode' => 'ANALYSE_ONLY', 'apply_enabled' => false,
            'guarantee' => 'NO_PORTAL_OR_BINDING_MUTATIONS', 'workbooks' => ['master' => $masterMeta, 'call_off' => $callMeta],
            'summary' => ['master_source_sites' => count($masterSites), 'qualifying_sites' => count($qualifying),
                'zero_call_sites_excluded' => count($excluded), 'sites_held_for_review' => count($review),
                'call_off_only_sites_held_for_review' => count(array_filter($review, fn ($site) => $site['master_site_reference'] === null)), 'master_plots_on_qualifying_sites' => count($includedPlotKeys),
                'qualifying_plots_with_calloffs' => array_sum(array_column($qualifying, 'plots_with_calloffs')),
                'qualifying_plots_without_calloffs' => array_sum(array_column($qualifying, 'plots_without_calloffs')),
                'call_only_plots_for_review' => count($callOnlyPlots), 'product_conflicts_for_review' => count($productConflicts),
                'master_rows_without_site_reference' => count(array_filter($masterRows, fn ($row): bool => ! isset($row['site_reference']) || $row['site_reference'] === '')),
                'call_rows_without_customer_code' => $callMeta['missing_customer_code_rows']],
            'sites' => $sites, 'product_conflicts' => $productConflicts, 'verified_examples' => $examples];
        $fingerprint = Canonical::evidenceHash($content);

        return ['content' => $content, 'fingerprint' => $fingerprint];
    }

    private function masterSitePlot(mixed $raw, mixed $preferredCode): array
    {
        if (! is_string($raw) || ! str_contains($raw, '-')) {
            return ['valid' => false, 'issue' => 'MASTER_SITE_PLOT_IDENTIFIER_INVALID'];
        }
        [$legacyCode, $plotRef] = explode('-', $raw, 2);
        $preferredCode = is_string($preferredCode) ? trim($preferredCode) : '';
        $customerNumber = trim($preferredCode !== '' ? $preferredCode : $legacyCode);
        $plotRef = trim($plotRef);
        if ($customerNumber === '' || $plotRef === '' || preg_match('/[\x00-\x1f\x7f<>]/', $customerNumber.$plotRef)) {
            return ['valid' => false, 'issue' => 'MASTER_SITE_PLOT_IDENTIFIER_INVALID'];
        }

        return ['valid' => true, 'site_reference' => $customerNumber, 'plot' => SourceIdentity::plotReference($plotRef)];
    }

    /** Master column Z is the preferred code; AA provides its legacy prefix and plot suffix. */
    private function masterHierarchy(mixed $raw, ?string $customerNumber): array
    {
        return (new MasterPlotHierarchy)->parse($raw, $customerNumber);
    }

    private function requiredColumns(array $header, array $labels): array
    {
        $columns = [];
        foreach ($labels as $label) {
            $matches = [];
            foreach ($header as $column => $cell) {
                if (is_string($cell->rawValue) && trim($cell->rawValue) === $label) {
                    $matches[] = (int) $column;
                }
            }
            if (count($matches) !== 1) {
                throw new ImportConflict('paired_required_header_unresolved');
            }
            $columns[$label] = $matches[0];
        }

        return $columns;
    }

    private function productColumns(array $header, bool $master = false): array
    {
        $dictionary = new CustomerAppDictionary;
        $columns = [];
        foreach ($header as $column => $cell) {
            if ($master && $column === 26) {
                continue;
            }
            if (! is_string($cell->rawValue) || ! preg_match('/^[A-Z0-9]{2,4}$/D', trim($cell->rawValue))) {
                continue;
            }
            $code = trim($cell->rawValue);
            $probe = $dictionary->product($code, 0);
            if ($probe->isResolved() && ($probe->match['group'] ?? null) !== 'EXCLUDED') {
                $columns[$code] = (int) $column;
            }
        }

        return $columns;
    }

    private function quantities(array $cells, array $columns): array
    {
        $dictionary = new CustomerAppDictionary;
        $facts = [];
        foreach ($columns as $code => $column) {
            $cell = $cells[$column] ?? null;
            if (! $cell || $cell->rawValue === null || is_string($cell->rawValue) && trim($cell->rawValue) === '') {
                continue;
            }
            $result = $dictionary->product($code, $cell->rawValue);
            if ($result->isResolved() && $result->classification->value !== 'ignored') {
                $facts[$code] = $result->value;
            }
        }

        return $facts;
    }

    private function productConflicts(array $facts): array
    {
        $assertions = [];
        foreach ($facts as $fact) {
            foreach ($fact['products'] as $code => $quantity) {
                $units = Quantity::units($quantity);
                if ($units !== null) {
                    $key = $fact['code']."\0".$fact['plot'];
                    $assertions[$key][$code][(string) $units][] = ['source' => $fact['source'], 'row' => $fact['row'],
                        'call' => $fact['call'], 'quantity' => Quantity::decimal($units)];
                }
            }
        }
        $conflicts = [];
        foreach ($assertions as $key => $products) {
            [$customerCode, $plot] = explode("\0", $key, 2);
            foreach ($products as $code => $values) {
                if (count($values) > 1) {
                    $conflicts[] = ['customer_code' => $customerCode, 'plot_ref' => $plot, 'product_code' => $code,
                        'assertions' => array_merge(...array_values($values))];
                }
            }
        }

        return $conflicts;
    }

    private function hierarchyKey(?string $customer, ?string $site): string
    {
        return MasterSourceResolver::normalizedName((string) $customer)."\0".MasterSourceResolver::normalizedName((string) $site);
    }

    private function metadata(object $sheet, array $table, array $artifact, string $sourceProfile): array
    {
        return ['sha256' => $artifact['workbook_hash'], 'bytes' => $artifact['byte_count'], 'sheet' => $sheet->id,
            'worksheet_rows' => count($sheet->cells), 'table' => $table['canonical_reference'],
            'source_profile' => $sourceProfile, 'format' => $artifact['format']];
    }
}
