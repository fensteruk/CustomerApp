<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\AnalysisSnapshot;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Semantics\Data\ObservedCell;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\SourceImport\Semantics\SemanticAdapter;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;

/** Reader-derived immutable evidence. No Portal queries, mutations or browser supplied facts. */
final class WorkbookStager
{
    public function inspect(object $run): array
    {
        $path = (new PrivateWorkbookStorage)->path($run);
        $budget = new AnalysisBudget;
        $profile = app(WorkbookProfiler::class)->profile($path, $run->format, $budget);
        $source = (new WorkbookSourceFactory)->open($path, $run->format, $budget);
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
        $snapshot = AnalysisSnapshot::fromProfile($profile, $observations);

        return [$profile, $sheets, $snapshot];
    }

    public function rows(object $run, array $inspection, array $knowledge): array
    {
        [$profile, $sheets, $snapshot] = $inspection;
        if ($snapshot->data['analysis_hash'] !== $knowledge['analysis_hash']) {
            throw new ImportConflict('analysis_changed');
        }
        $data = $snapshot->data;
        if (count($data['tables']) !== 1 || count($sheets) !== 1) {
            throw new ImportConflict('bounded_single_table_required');
        }
        $tableId = array_key_first($data['tables']);
        $table = $data['tables'][$tableId];
        $sheet = array_values($sheets)[0];
        if ($table['warnings'] !== [] || $sheet->visibility !== 'visible' || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
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
        foreach (['call_reference', 'plot_reference', 'call_type', 'completion'] as $role) {
            if (! isset($columns[$role])) {
                throw new ImportConflict('required_column_unresolved');
            }
        }
        $siteRole = isset($columns['source_site_identity']) ? 'source_site_identity' : 'transitional_site_clue';
        if (! isset($columns[$siteRole])) {
            throw new ImportConflict('required_site_column_unresolved');
        }
        $dictionary = new CustomerAppDictionary;
        $rows = [];
        $seen = [];
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber <= $table['header_range']['end_row']) {
                continue;
            }
            if (! array_filter($cells, fn ($c) => $c->rawValue !== null && $c->rawValue !== '')) {
                continue;
            }
            if (count($rows) >= BackendStore::MAX_ROWS) {
                throw new ImportConflict('explicit_run_row_limit_exceeded');
            }
            $raw = fn ($role) => isset($columns[$role]) ? ($cells[$columns[$role]]->rawValue ?? null) : null;
            $call = $raw('call_reference');
            $plot = $raw('plot_reference');
            $site = $raw($siteRole);
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
            foreach (['plot' => $plot, 'site' => $site] as $name => $value) {
                if ((! is_string($value) && ! is_int($value)) || trim((string) $value) === '' || mb_strlen((string) $value) > ($name === 'plot' ? 200 : 512) || preg_match('/[\x00-\x1f\x7f<>]/', (string) $value)) {
                    $issues[] = 'INVALID_'.strtoupper($name);
                }
            }
            $selection = (new ReviewedWorkbookSelection)->treatment($run->workbook_hash, $sheet->id, $rowNumber, $call, (string) $site, is_string($callType) ? $callType : '');
            $semanticApproval = null;
            $cell = $cells[$columns['call_type']] ?? null;
            if ($cell && $selection['canonical_override'] === null && $callType === 'CC!') {
                $key = 'occurrence:'.Canonical::hash((new ObservedCell($run->workbook_hash, $sheet->id, $cell))->jsonSerialize());
                $semanticApproval = $knowledge['selections'][$key] ?? null;
            }
            $effectiveType = $selection['canonical_override'] ?? $semanticApproval['canonical'] ?? $callType;
            $type = $dictionary->callType(is_string($effectiveType) ? $effectiveType : '');
            $complete = $dictionary->completion($raw('completion'));
            if (! $type->isResolved()) {
                $issues[] = 'UNRESOLVED_CALL_TYPE';
            }
            if (! $complete->isResolved()) {
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
                $product = $dictionary->product($code, $value);
                $rawProducts[$code] = ['presence' => $value === null || $value === '' ? 'PRESENT_BLANK' : 'PRESENT_VALUE', 'raw' => $value];
                if (($product->match['group'] ?? null) === 'EXCLUDED') {
                    continue;
                }
                $sourceCell = $cells[$column] ?? null;
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
            foreach (['call_type', 'completion'] as $role) {
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
            $excluded = $selection['excluded'];
            $facts = ['call_number' => $call, 'source_site' => (string) $site, 'site_kind' => $siteRole === 'source_site_identity' ? 'SOURCE_SITE_ID' : 'EXACT_SITE_NAME',
                'plot' => (string) $plot, 'service' => $type->value, 'call_type' => $type->lookupValue, 'complete' => $complete->value, 'products' => $products];
            $rows[] = ['canonical' => $excluded ? ['excluded' => true, 'call_number' => $call] : $facts, 'facts' => $facts, 'excluded' => $excluded,
                'issues' => $excluded ? array_values(array_intersect($issues, ['DUPLICATE_CALL_NUMBER', 'INVALID_CALL_NUMBER', 'UNSAFE_CELL'])) : array_values(array_unique($issues)),
                'provenance' => ['sheet' => $sheet->id, 'row' => $rowNumber, 'raw_call_type' => $callType, 'raw_complete' => $raw('completion'),
                    'raw_products' => $rawProducts, 'operational_date' => $raw('pc1_operational_install_date'), 'selection' => $selection, 'semantic_answer' => $semanticApproval, 'semantic_evidence' => $semanticEvidence]];
        }
        if ($rows === []) {
            throw new ImportConflict('no_source_records');
        }

        return $rows;
    }
}
