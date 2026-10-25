<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\Wald\Contracts\SheetObservation;

/** Analyse-only logical roles; the original physical sheet is never rewritten. */
final class GroupedPlotObservations
{
    public const PROFILE = 'customerapp.redzebra-grouped.analyse-only.v1';

    public const NATIVE_PROFILE = 'customerapp.redzebra-grouped-native.v1';

    public static function isZeroCallSource(array $source): bool
    {
        return ($source['source_profile'] ?? null) === self::NATIVE_PROFILE
            && (int) ($source['calloff_rows'] ?? 0) === 0
            && (int) ($source['activity_ambiguous_rows'] ?? 0) === 0;
    }

    public function matches(SheetObservation $sheet, array $table): bool
    {
        $header = $sheet->cells[$table['header_range']['start_row']] ?? [];
        foreach ([1 => 'Call No.', 2 => 'Site Name', 3 => 'Plot Ref', 4 => 'Call Type',
            27 => 'Customer Number', 28 => 'Site Number'] as $column => $label) {
            if (trim($header[$column]->rawValue ?? '') !== $label) {
                return false;
            }
        }
        foreach ($sheet->cells as $number => $cells) {
            if ($number > $table['header_range']['end_row'] && $this->parent($cells)
                && (new CompositePlotHierarchy)->parse($cells[2]->rawValue)['valid']) {
                return true;
            }
        }

        return false;
    }

    public function classify(SheetObservation $sheet, array $table, bool $allowMalformedParentHierarchy = false): array
    {
        $rows = [];
        $parent = null;
        $children = [];
        $flush = function () use ($sheet, &$rows, &$parent, &$children): void {
            if ($parent === null) {
                return;
            }
            $code = null;
            $identityRow = null;
            $preferredCodeCell = null;
            $sourceCustomerCodeRaw = null;
            foreach ($children as $number) {
                $preferredCode = $sheet->cells[$number][26]->rawValue ?? null;
                $legacyCode = $sheet->cells[$number][27]->rawValue ?? null;
                $evidence = $this->codeEvidence($preferredCode, $legacyCode);
                $code = $evidence['code'];
                if ($code !== null) {
                    $identityRow = $number;
                    $preferredCodeCell = $evidence['column'] === 'Z' ? 'Z'.$number : null;
                    $sourceCustomerCodeRaw = $evidence['raw'];
                    break;
                }
            }
            $category = trim($sheet->cells[$parent][1]->rawValue);
            $excluded = in_array($category, ['Private One Off', 'Trade Supply Only'], true);
            $rawPlot = $sheet->cells[$parent][2]->rawValue;
            $rows[$parent] = $this->row($parent, $parent, $identityRow, $code, $rawPlot, null, null,
                $code === null ? 'GROUPED_CUSTOMER_CODE_MISSING' : null, $excluded, 'PARENT_PLOT', $preferredCodeCell,
                $sourceCustomerCodeRaw);
            $rows[$parent]['master_site_name'] = $category;
            $fenced = false;
            foreach ($children as $number) {
                $cells = $sheet->cells[$number];
                $preferredCode = $cells[26]->rawValue ?? null;
                $legacyCode = $cells[27]->rawValue ?? null;
                $evidence = $this->codeEvidence($preferredCode, $legacyCode);
                $childCode = $evidence['code'];
                $issue = null;
                if ($childCode === null) {
                    $issue = 'GROUPED_CUSTOMER_CODE_MISSING';
                } elseif ($childCode !== $code) {
                    $issue = 'GROUPED_PARENT_SITE_MISMATCH';
                    $fenced = true;
                } elseif ($fenced) {
                    $issue = 'GROUPED_ORPHAN_ROW';
                }
                $rows[$number] = $this->row($number, $parent, $number, $childCode, $rawPlot,
                    $cells[1]->rawValue ?? null, $cells[2]->rawValue ?? null, $issue, $excluded, 'CHILD_DETAIL',
                    $evidence['column'] === 'Z' ? 'Z'.$number : null, $evidence['raw']);
                $rows[$number]['master_site_name'] = $category;
            }
        };
        $start = $table['data_start_row'] ?? $table['header_range']['end_row'] + 1;
        $end = $table['data_end_row'] ?? $table['range']['end_row'];
        foreach ($sheet->cells as $number => $cells) {
            if ($number < $start || $number > $end || ! array_filter($cells, fn ($cell) => $cell->hasContent())) {
                continue;
            }
            if ($this->parent($cells, $allowMalformedParentHierarchy)) {
                $flush();
                $parent = $number;
                $children = [];
            } elseif ($this->ambiguousParentCandidate($cells)) {
                $flush();
                $parent = null;
                $children = [];
                $evidence = $this->codeEvidence($cells[26]->rawValue ?? null, $cells[27]->rawValue ?? null);
                $rows[$number] = $this->row($number, null, $number, $evidence['code'], $cells[2]->rawValue,
                    $cells[1]->rawValue, null, 'GROUPED_PARENT_HIERARCHY_INVALID', false, 'AMBIGUOUS_PARENT',
                    $evidence['column'] === 'Z' ? 'Z'.$number : null, $evidence['raw']);
            } elseif ($parent !== null) {
                $children[] = $number;
            } else {
                $evidence = $this->codeEvidence($cells[26]->rawValue ?? null, $cells[27]->rawValue ?? null);
                $rows[$number] = $this->row($number, null, $number,
                    $evidence['code'], null,
                    $cells[1]->rawValue ?? null, $cells[2]->rawValue ?? null,
                    'GROUPED_ORPHAN_ROW', false, 'ORPHAN',
                    $evidence['column'] === 'Z' ? 'Z'.$number : null, $evidence['raw']);
            }
        }
        $flush();
        ksort($rows);

        return $rows;
    }

    public function eligibility(array $rows, array $callFindings): array
    {
        $states = [];
        foreach ($rows as $row) {
            $code = $row['source_customer_code'];
            if ($code === null || $row['excluded']) {
                continue;
            }
            if (is_string($row['call_type']) && CustomerAppDictionary::excludesCallType($row['call_type'])) {
                continue;
            }
            $states[$code] ??= ['typed' => false, 'unsafe' => false, 'qualifying' => false];
            $parsed = (new CompositePlotHierarchy)->parse($row['plot_reference'], $code);
            $category = PlotRegisterCapabilities::category($row['call_type']);
            $states[$code]['typed'] = $states[$code]['typed'] || $category !== 'BASE_PLOT_ROW';
            $states[$code]['unsafe'] = $states[$code]['unsafe'] || $row['issue'] !== null || ! $parsed['valid'];
            $states[$code]['qualifying'] = $states[$code]['qualifying']
                || ($row['issue'] === null && $parsed['valid'] && $row['call_reference'] !== null
                    && $row['call_reference'] !== ''
                    && ! isset($callFindings[$row['call_reference']]));
        }

        return array_map(fn ($state) => $state['qualifying'] ? 'ELIGIBLE'
            : (! $state['typed'] && ! $state['unsafe'] ? 'ZERO_CALLOFFS' : 'REVIEW'), $states);
    }

    private function parent(array $cells, bool $allowMalformedHierarchy = false): bool
    {
        $populated = array_keys(array_filter($cells, fn ($cell) => $cell->hasContent()));
        sort($populated);

        return $this->parentShape($cells, $populated)
            && ($allowMalformedHierarchy || (new CompositePlotHierarchy)->parse($cells[2]->rawValue)['valid']);
    }

    private function ambiguousParentCandidate(array $cells): bool
    {
        $populated = array_keys(array_filter($cells, fn ($cell) => $cell->hasContent()));
        sort($populated);

        return $this->parentShape($cells, $populated)
            && preg_match_all('/[-\x{2013}\x{2014}]/u', trim($cells[2]->rawValue)) >= 2
            && ! (new CompositePlotHierarchy)->parse($cells[2]->rawValue)['valid'];
    }

    private function parentShape(array $cells, array $populated): bool
    {
        return $populated === [1, 2] && ! preg_match('/^[0-9]+$/D', $cells[1]->rawValue)
            && trim($cells[1]->rawValue) !== '' && trim($cells[2]->rawValue) !== '';
    }

    private function codeEvidence(?string $preferred, ?string $legacy): array
    {
        if ($preferred !== null && trim($preferred) !== '') {
            $preferredCode = $this->validCode($preferred) ? $preferred : null;
            if ($preferredCode !== null) {
                return ['code' => $preferredCode, 'column' => 'Z', 'raw' => $preferred];
            }
        }

        if ($legacy !== null && str_contains($legacy, '-')) {
            $legacyCode = explode('-', $legacy, 2)[0];
            if ($this->validCode($legacyCode)) {
                return ['code' => $legacyCode, 'column' => 'AA', 'raw' => $legacy];
            }
        }

        return ['code' => null, 'column' => null, 'raw' => null];
    }

    private function validCode(string $code): bool
    {
        return $code !== '' && $code === trim($code) && mb_strlen($code) <= 512
            && ! preg_match('/[\x00-\x1f\x7f<>]/', $code);
    }

    private function row(int $number, ?int $parent, ?int $identityRow, ?string $code, ?string $plot,
        mixed $call, ?string $type, ?string $issue, bool $excluded, string $kind,
        ?string $preferredCodeCell = null, ?string $sourceCustomerCodeRaw = null): array
    {
        $rawCall = $call;
        if ($call !== null) {
            if (! is_string($call) && ! is_int($call)) {
                $issue ??= 'GROUPED_INVALID_CALL_NUMBER';
                $call = null;
            } else {
                $call = trim((string) $call);
                if ($call === '') {
                    $call = null;
                } elseif (mb_strlen($call) > 100 || preg_match('/[\x00-\x1f\x7f]/', $call)) {
                    $issue ??= 'GROUPED_INVALID_CALL_NUMBER';
                    $call = null;
                }
            }
        }
        if ($call === null && is_scalar($type) && trim((string) $type) !== '') {
            $issue ??= 'GROUPED_CALL_NUMBER_MISSING';
        }
        $parsed = (new CompositePlotHierarchy)->parse($plot, $code);

        return ['kind' => $kind, 'call_reference' => $call, 'call_type' => $type,
            'source_customer_code' => $code, 'plot_reference' => $plot,
            'transitional_site_clue' => $parsed['valid'] ? $parsed['site'] : null,
            'issue' => $issue, 'excluded' => $excluded,
            'provenance' => ['row' => $number, 'parent_row' => $parent,
                'plot_cell' => $parent === null ? null : 'B'.$parent,
                'identity_cell' => $identityRow === null ? null : 'AA'.$identityRow,
                'preferred_code_cell' => $preferredCodeCell,
                'source_customer_code_raw' => $sourceCustomerCodeRaw,
                'raw_call' => $rawCall,
                'raw_type' => $type,
                'call_cell' => $kind === 'PARENT_PLOT' ? null : 'A'.$number,
                'type_cell' => $kind === 'PARENT_PLOT' ? null : 'B'.$number]];
    }
}
