<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\AnalysisSnapshot;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Readers\XlsWorkbookSource;
use App\SourceImport\Semantics\Data\ObservedCell;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\SourceImport\Semantics\SemanticAdapter;
use App\Wald\Contracts\SourceRange;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Illuminate\Support\Facades\DB;

/** Reader-derived immutable evidence. No Portal queries, mutations or browser supplied facts. */
final class WorkbookStager
{
    public function inspect(object $run): array
    {
        $path = (new PrivateWorkbookStorage)->path($run);
        $budget = new AnalysisBudget;
        if ($run->format === 'xls') {
            $source = new XlsWorkbookSource($path, $budget);
            try {
                $profile = app(WorkbookProfiler::class)->analyse($source, $run->workbook_hash, $budget);
            } finally {
                $source->close();
            }
        } else {
            $profile = app(WorkbookProfiler::class)->profile($path, $run->format, $budget);
        }
        $source = $run->format === 'xls'
            ? new XlsWorkbookSource($path, $budget)
            : (new WorkbookSourceFactory)->open($path, $run->format, $budget);
        try {
            $sheets = iterator_to_array($source->sheets());
        } finally {
            $source->close();
        }
        $observations = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet->cells as $row) {
                foreach ($row as $cell) {
                    // Only unresolved, offered one-time meaning needs occurrence questions.
                    if ($cell->rawValue === 'CC!') {
                        $observations[] = new ObservedCell($run->workbook_hash, $sheet->id, $cell);
                    }
                }
            }
        }
        $snapshot = AnalysisSnapshot::fromProfile($profile, $observations, $sheets);

        return [$profile, $sheets, $snapshot];
    }

    public function rows(object $run, array $inspection, array $knowledge): array
    {
        [$profile, $sheets, $snapshot] = $inspection;
        if ($snapshot->data['analysis_hash'] !== $knowledge['analysis_hash']) {
            throw new ImportConflict('analysis_changed');
        }
        $data = $snapshot->data;
        if (($data['composite_candidate_count'] ?? 0) > 1) {
            throw new ImportConflict('ambiguous_composite_table');
        }
        if (count($data['tables']) !== 1 || count($sheets) !== 1) {
            throw new ImportConflict('bounded_single_table_required');
        }
        $tableId = array_key_first($data['tables']);
        $table = $data['tables'][$tableId];
        $sheet = collect($sheets)->firstWhere('id', $table['sheet_id']);
        if ($sheet === null) {
            throw new ImportConflict('source_sheet_changed');
        }
        $headerNeedsConfirmation = in_array('no_clear_header', $table['warnings'], true);
        if (array_diff($table['warnings'], ['no_clear_header']) !== [] || $sheet->visibility !== 'visible' || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
            throw new ImportConflict('unsafe_or_unsupported_structure');
        }
        $reasoning = (new ReasoningEngine)->reason($profile);
        $reason = $reasoning->toArray();
        $columns = [];
        $confirmed = [];
        foreach ($data['questions'] as $key => $question) {
            if ($question['type'] !== 'STRUCTURAL') {
                continue;
            }
            if ($key === 'structure:ignored') {
                continue;
            }
            $candidate = $knowledge['selections'][$key] ?? null;
            $explicit = array_key_exists($key, $knowledge['selections']);
            if (! $explicit && count($question['candidates']) === 1) {
                $possible = $question['candidates'][0];
                $matches = array_filter($reason['hypotheses'], fn ($h) => ($h['hypothesis']['target']['column'] ?? null) === $possible['column'] && ($h['hypothesis']['target']['sheet_id'] ?? null) === $sheet->id && $h['decision'] === 'accepted' && ! in_array(true, $h['confirmation_flags'] ?? [], true));
                if (count($matches) === 1) {
                    $candidate = $possible;
                }
            }
            if ($candidate === null || $candidate['table'] !== $tableId) {
                throw new ImportConflict('structural_clarification_required');
            }
            $role = $candidate['role'];
            $columns[$role] = $candidate['column'];
            $confirmed[$role] = $explicit;
        }
        foreach (['call_reference', 'plot_reference', 'call_type'] as $role) {
            if (! isset($columns[$role])) {
                throw new ImportConflict('required_column_unresolved');
            }
        }
        $roleCandidates = [];
        foreach ($columns as $role => $column) {
            $roleCandidates[$role][(int) $column] = true;
        }
        $sourceIdentity = (new MasterExportSiteIdentity)->columns($roleCandidates, $run->workbook_hash, isset($run->pilot_upload_id));
        if ($headerNeedsConfirmation && count(array_filter($confirmed)) !== count($confirmed)) {
            throw new ImportConflict('structural_clarification_required');
        }
        $dictionary = new CustomerAppDictionary;
        $target = DB::table('sites')->join('customer_organisations', 'customer_organisations.id', '=', 'sites.customer_organisation_id')
            ->where('sites.id', $run->site_id)
            ->first(['sites.name as site_name', 'customer_organisations.name as customer_name']);
        if (! $target) {
            throw new ImportConflict('source_site_binding_required');
        }
        $compositeMode = false;
        $hierarchyAnswers = [];
        $restoredRows = [];
        if ($run->pilot_upload_id ?? null) {
            $restoredRows = array_fill_keys(DB::table('wald_pilot_ignored_rows')
                ->where('pilot_upload_id', $run->pilot_upload_id)->where('disposition', 'RESTORED')
                ->pluck('row_number')->map(fn ($row): int => (int) $row)->all(), true);
            $manifest = DB::table('wald_pilot_uploads')->where('id', $run->pilot_upload_id)->value('source_manifest');
            $decoded = is_string($manifest) ? json_decode($manifest, true, flags: JSON_THROW_ON_ERROR) : [];
            $compositeMode = collect($decoded['sources'] ?? [])->contains(
                fn (array $source): bool => ($source['hash'] ?? null) === $run->source_site_filter_hash
                    && ($source['hierarchy_mode'] ?? null) === 'COMPOSITE',
            );
            if ($compositeMode) {
                $hierarchyAnswers = (new HierarchyClarifications)->all($run->pilot_upload_id)[$run->source_site_filter_hash] ?? [];
            }
        }
        $rows = [];
        $seen = [];
        $dataStart = (int) ($table['data_start_row'] ?? ($table['header_range']['end_row'] + 1));
        $dataEnd = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        $fragmentByColumn = [];
        foreach ($table['fragments'] ?? [['id' => 1, 'data_range' => $table['range']]] as $fragment) {
            for ($column = $fragment['data_range']['start_column']; $column <= $fragment['data_range']['end_column']; $column++) {
                $fragmentByColumn[$column] = $fragment['id'];
            }
        }
        $roleByColumn = array_flip($columns);
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataEnd) {
                continue;
            }
            if (! array_filter($cells, fn ($c) => isset($fragmentByColumn[$c->column]) && $c->hasContent())) {
                continue;
            }
            $raw = fn ($role) => isset($columns[$role]) ? ($cells[$columns[$role]]->rawValue ?? null) : null;
            $call = $raw('call_reference');
            $plot = $raw('plot_reference');
            $sourceIdentityValue = $cells[$sourceIdentity['identity_column']]->rawValue ?? null;
            $siteName = (new MasterExportSiteIdentity)->siteName(
                $sourceIdentity['site_name_column'] === null ? null : ($cells[$sourceIdentity['site_name_column']]->rawValue ?? null),
            );
            $callType = $raw('call_type');
            $issues = [];
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    $issues[] = 'UNSAFE_CELL';
                }
            }
            if (! (is_int($call) || is_string($call)) || ! preg_match('/^[0-9]{1,100}$/D', (string) $call) || ! preg_match('/[1-9]/', (string) $call)) {
                $issues[] = 'INVALID_CALL_NUMBER';
            }
            $call = (string) $call;
            if (isset($seen[$call])) {
                $issues[] = 'DUPLICATE_CALL_NUMBER';
            }
            $seen[$call] = true;
            if ((! is_string($plot) && ! is_int($plot)) || trim((string) $plot) === '' || mb_strlen((string) $plot) > 200 || preg_match('/[\x00-\x1f\x7f<>]/', (string) $plot)) {
                $issues[] = 'INVALID_PLOT';
            }
            $plot = SourceIdentity::plotReference((string) $plot);
            // An excluded row without a source identity cannot belong to this selected site.
            // Discovery has already retained its original workbook and checked CallNo uniqueness.
            if (is_string($callType) && CustomerAppDictionary::excludesCallType($callType)
                && ($sourceIdentityValue === null || trim((string) $sourceIdentityValue) === '')) {
                continue;
            }
            $normalisedSite = $sourceIdentity['kind'] === MasterExportSiteIdentity::KIND
                ? (new MasterExportSiteIdentity)->customerCode($sourceIdentityValue)
                : trim((string) $sourceIdentityValue);
            if ($sourceIdentity['kind'] === MasterExportSiteIdentity::KIND && CustomerAppDictionary::excludesCustomerCode($normalisedSite)
                && ! isset($restoredRows[(int) $rowNumber])) {
                continue;
            }
            if ($normalisedSite === '' || mb_strlen($normalisedSite) > 512 || preg_match('/[\x00-\x1f\x7f<>]/', $normalisedSite)) {
                $issues[] = 'INVALID_SITE';
            }
            if ($run->source_site_filter_hash ?? null) {
                $observedHash = Canonical::hash([$sourceIdentity['kind'], $normalisedSite]);
                if (! hash_equals($run->source_site_filter_hash, $observedHash)
                    || ! hash_equals((string) $run->source_site_filter, $normalisedSite)) {
                    continue;
                }
            }
            if (count($rows) >= BackendStore::MAX_ROWS) {
                throw new ImportConflict('explicit_run_row_limit_exceeded');
            }
            $selection = (new ReviewedWorkbookSelection)->treatment($run->workbook_hash, $sheet->id, $rowNumber, $call, $siteName ?? $normalisedSite, is_string($callType) ? $callType : '');
            $excluded = $selection['excluded'];
            $rawPlot = $raw('plot_reference');
            $hierarchy = $excluded || ! $compositeMode ? null : (new CompositePlotHierarchy)->parse($rawPlot, $normalisedSite);
            if ($hierarchy !== null && ! $hierarchy['valid'] && isset($hierarchyAnswers[Canonical::hash([$rawPlot])])) {
                $answer = $hierarchyAnswers[Canonical::hash([$rawPlot])];
                $hierarchy = ['valid' => true, 'customer' => $answer['customer'], 'site' => $answer['site'],
                    'plot_source' => $rawPlot, 'plot' => $answer['plot'], 'raw' => $rawPlot, 'office_confirmed' => true];
            }
            if ($hierarchy !== null) {
                if (! $hierarchy['valid']) {
                    $issues[] = $hierarchy['issue'];
                } elseif (! MasterSourceResolver::sameName($hierarchy['customer'], $target->customer_name)
                    || ! MasterSourceResolver::sameName($hierarchy['site'], $target->site_name)) {
                    $issues[] = 'SOURCE_BINDING_CUSTOMER_OWNERSHIP_CONFLICT';
                } else {
                    $plot = $hierarchy['plot'];
                }
            }
            $semanticApproval = null;
            $cell = $cells[$columns['call_type']] ?? null;
            if ($cell && $selection['canonical_override'] === null && $callType === 'CC!') {
                $key = 'occurrence:'.Canonical::hash((new ObservedCell($run->workbook_hash, $sheet->id, $cell))->jsonSerialize());
                $semanticApproval = $knowledge['selections'][$key] ?? null;
            }
            $effectiveType = $selection['canonical_override'] ?? $semanticApproval['canonical'] ?? $callType;
            $type = $dictionary->callType(
                is_scalar($effectiveType) ? (string) $effectiveType : '',
                ($table['composite'] ?? false) ? CustomerAppDictionary::COMPOSITE_PROFILE : null,
            );
            $hasVisit = is_scalar($effectiveType) && trim((string) $effectiveType) !== '';
            $complete = $hasVisit && isset($columns['completion']) ? $dictionary->completion($raw('completion')) : null;
            if (! $type->isResolved()) {
                $issues[] = 'UNRESOLVED_CALL_TYPE';
            }
            if ($complete !== null && ! $complete->isResolved()) {
                $issues[] = 'UNRESOLVED_COMPLETION';
            }
            $products = [];
            $rawProducts = [];
            foreach ($columns as $role => $column) {
                if (! str_starts_with($role, 'quantity:')) {
                    continue;
                }
                $code = substr($role, 9);
                $value = $raw($role);
                $sourceCell = $cells[$column] ?? null;
                $represented = $sourceCell !== null && ! (is_string($value) && trim($value) === '');
                $rawProducts[$code] = ['presence' => $represented ? 'PRESENT_VALUE' : 'UNREPRESENTED', 'raw' => $value];
                if (! $represented) {
                    continue;
                }
                $product = $dictionary->product($code, $value);
                if (($product->match['group'] ?? null) === 'EXCLUDED') {
                    continue;
                }
                if ($sourceCell && in_array((new ValueProfiler)->type($sourceCell), ['date_like', 'percentage_like', 'boolean_like', 'formula', 'error'], true)) {
                    $issues[] = 'INVALID_PRODUCT_CELL_TYPE';

                    continue;
                }
                if (! $product->isResolved()) {
                    $issues[] = 'INVALID_PRODUCT_QUANTITY';
                } else {
                    $products[$code] = $product->value;
                }
            }
            if (! $dictionary->rollup($products)->resolved) {
                $issues[] = 'PRODUCT_ROLLUP_INVALID';
            }
            // Compose WALD03 evidence without altering the accepted reasoning or its decisions.
            $semanticEvidence = [];
            foreach ($hasVisit ? array_values(array_intersect(['call_type', 'completion'], array_keys($columns))) : [] as $role) {
                $cell = $cells[$columns[$role]] ?? null;
                $hypothesis = collect($reason['hypotheses'])->first(fn ($h) => ($h['hypothesis']['target']['column'] ?? null) === $columns[$role] && ($h['hypothesis']['target']['sheet_id'] ?? null) === $sheet->id);
                if ($cell && $hypothesis) {
                    $original = (new SemanticAdapter)->interpret(new ObservedCell($run->workbook_hash, $sheet->id, $cell), $reasoning, $hypothesis['hypothesis']['id']);
                    $semanticEvidence[$role] = ['classification' => $original->classification->value, 'resolution' => $original->resolution->value, 'reasons' => $original->reasons];
                    if (! $original->isResolved() && ! $confirmed[$role] && ! ($role === 'call_type' && ($selection['canonical_override'] || $semanticApproval))) {
                        $issues[] = 'WALD_SEMANTIC_CONFIRMATION_REQUIRED';
                    }
                }
            }
            $facts = ['call_number' => $call, 'source_site' => $normalisedSite, 'site_kind' => $sourceIdentity['kind'],
                'plot' => $plot, 'service' => $hasVisit && $type->isResolved() ? $type->value : null,
                'call_type' => $hasVisit && $type->isResolved() ? $type->lookupValue : null,
                'complete' => $complete?->isResolved() ? $complete->value : null, 'products' => $products,
                'hierarchy_customer' => ($hierarchy['valid'] ?? false) ? $hierarchy['customer'] : null,
                'hierarchy_site' => ($hierarchy['valid'] ?? false) ? $hierarchy['site'] : null];
            $valueProvenance = [];
            foreach ($columns as $role => $column) {
                $cell = $cells[$column] ?? null;
                $valueProvenance[$role] = ['sheet' => $sheet->id,
                    'cell' => SourceRange::columnLetters($column).$rowNumber,
                    'raw' => $cell?->rawValue,
                    'logical_row' => $rowNumber - $dataStart + 1,
                    'fragment' => $fragmentByColumn[$column] ?? null];
            }
            $unmapped = [];
            foreach ($cells as $column => $cell) {
                if (isset($fragmentByColumn[$column]) && ! isset($roleByColumn[$column]) && $cell->hasContent()) {
                    $unmapped[] = ['sheet' => $sheet->id, 'cell' => SourceRange::columnLetters($column).$rowNumber,
                        'raw' => $cell->rawValue, 'logical_row' => $rowNumber - $dataStart + 1,
                        'fragment' => $fragmentByColumn[$column]];
                }
            }
            $rows[] = ['canonical' => $excluded ? ['excluded' => true, 'call_number' => $call] : $facts, 'facts' => $facts, 'excluded' => $excluded,
                'issues' => $excluded ? array_values(array_intersect($issues, ['DUPLICATE_CALL_NUMBER', 'INVALID_CALL_NUMBER', 'UNSAFE_CELL'])) : array_values(array_unique($issues)),
                'provenance' => ['sheet' => $sheet->id, 'row' => $rowNumber, 'raw_call_type' => $callType, 'raw_complete' => $raw('completion'),
                    'source_site_name' => $siteName,
                    'parsed_hierarchy' => $hierarchy,
                    'logical_table' => $table['canonical_reference'] ?? $table['range']['address'],
                    'values' => $valueProvenance, 'unmapped_private_evidence' => $unmapped,
                    'raw_products' => $rawProducts, 'operational_date' => $raw('pc1_operational_install_date'), 'selection' => $selection, 'semantic_answer' => $semanticApproval, 'semantic_evidence' => $semanticEvidence]];
        }
        if ($rows === []) {
            throw new ImportConflict('no_source_records');
        }

        $consolidated = (new ProductConsolidator)->consolidate($rows);
        if ($consolidated['conflicts'] !== []) {
            foreach ($rows as &$row) {
                if (isset($consolidated['conflicts'][$row['facts']['plot']])) {
                    $row['issues'][] = 'PRODUCT_QUANTITY_CONFLICT';
                    $row['issues'] = array_values(array_unique($row['issues']));
                }
            }
            unset($row);
        }

        return $rows;
    }
}
