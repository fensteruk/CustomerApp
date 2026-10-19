<?php

namespace App\SourceImport\Integration;

use App\Wald\Contracts\SheetObservation;

/** Analyse-only logical roles; the original physical sheet is never rewritten. */
final class GroupedPlotObservations
{
    public const PROFILE = 'customerapp.redzebra-grouped.analyse-only.v1';

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

    public function classify(SheetObservation $sheet, array $table): array
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
            foreach ($children as $number) {
                $code = $this->code($sheet->cells[$number][26]->rawValue ?? null,
                    $sheet->cells[$number][27]->rawValue ?? null);
                if ($code !== null) {
                    $identityRow = $number;
                    break;
                }
            }
            $category = trim($sheet->cells[$parent][1]->rawValue);
            $excluded = in_array($category, ['Private One Off', 'Trade Supply Only'], true);
            $rawPlot = $sheet->cells[$parent][2]->rawValue;
            $rows[$parent] = $this->row($parent, $parent, $identityRow, $code, $rawPlot, null, null,
                $code === null ? 'GROUPED_CUSTOMER_CODE_MISSING' : null, $excluded, 'PARENT_PLOT');
            $rows[$parent]['master_site_name'] = $category;
            $fenced = false;
            foreach ($children as $number) {
                $cells = $sheet->cells[$number];
                $childCode = $this->code($cells[26]->rawValue ?? null, $cells[27]->rawValue ?? null);
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
                    $cells[1]->rawValue ?? null, $cells[2]->rawValue ?? null, $issue, $excluded, 'CHILD_DETAIL');
                $rows[$number]['master_site_name'] = $category;
            }
        };
        $start = $table['data_start_row'] ?? $table['header_range']['end_row'] + 1;
        $end = $table['data_end_row'] ?? $table['range']['end_row'];
        foreach ($sheet->cells as $number => $cells) {
            if ($number < $start || $number > $end || ! array_filter($cells, fn ($cell) => $cell->hasContent())) {
                continue;
            }
            if ($this->parent($cells)) {
                $flush();
                $parent = $number;
                $children = [];
            } elseif ($parent !== null) {
                $children[] = $number;
            } else {
                $rows[$number] = $this->row($number, null, $number,
                    $this->code($cells[26]->rawValue ?? null, $cells[27]->rawValue ?? null), null,
                    $cells[1]->rawValue ?? null, $cells[2]->rawValue ?? null,
                    'GROUPED_ORPHAN_ROW', false, 'ORPHAN');
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
            $states[$code] ??= ['typed' => false, 'unsafe' => false, 'qualifying' => false];
            $parsed = (new CompositePlotHierarchy)->parse($row['plot_reference'], $code);
            $category = PlotRegisterCapabilities::category($row['call_type']);
            $states[$code]['typed'] = $states[$code]['typed'] || $category !== 'BASE_PLOT_ROW';
            $states[$code]['unsafe'] = $states[$code]['unsafe'] || $row['issue'] !== null || ! $parsed['valid'];
            $states[$code]['qualifying'] = $states[$code]['qualifying']
                || ($row['issue'] === null && $parsed['valid'] && $row['call_reference'] !== null
                    && $row['call_reference'] !== '' && $category === 'RECOGNISED_CALLOFF_ROW'
                    && ! isset($callFindings[$row['call_reference']]));
        }

        return array_map(fn ($state) => $state['qualifying'] ? 'ELIGIBLE'
            : (! $state['typed'] && ! $state['unsafe'] ? 'ZERO_CALLOFFS' : 'REVIEW'), $states);
    }

    private function parent(array $cells): bool
    {
        $populated = array_keys(array_filter($cells, fn ($cell) => $cell->hasContent()));
        sort($populated);

        return $populated === [1, 2] && ! preg_match('/^[0-9]+$/D', $cells[1]->rawValue)
            && trim($cells[1]->rawValue) !== '' && trim($cells[2]->rawValue) !== '';
    }

    private function code(?string $preferred, ?string $legacy): ?string
    {
        if ($preferred !== null && trim($preferred) !== '') {
            $code = $preferred;
        } elseif ($legacy !== null && str_contains($legacy, '-')) {
            $code = explode('-', $legacy, 2)[0];
        } else {
            return null;
        }

        if ($code === '' || $code !== trim($code) || mb_strlen($code) > 512
            || preg_match('/[\x00-\x1f\x7f<>]/', $code)) {
            return null;
        }

        return $code;
    }

    private function row(int $number, ?int $parent, ?int $identityRow, ?string $code, ?string $plot,
        mixed $call, ?string $type, ?string $issue, bool $excluded, string $kind): array
    {
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
        $parsed = (new CompositePlotHierarchy)->parse($plot, $code);

        return ['kind' => $kind, 'call_reference' => $call, 'call_type' => $type,
            'source_customer_code' => $code, 'plot_reference' => $plot,
            'transitional_site_clue' => $parsed['valid'] ? $parsed['site'] : null,
            'issue' => $issue, 'excluded' => $excluded,
            'provenance' => ['row' => $number, 'parent_row' => $parent,
                'plot_cell' => $parent === null ? null : 'B'.$parent,
                'identity_cell' => $identityRow === null ? null : 'AA'.$identityRow,
                'preferred_code_cell' => $identityRow === null ? null : 'Z'.$identityRow,
                'call_cell' => $kind === 'PARENT_PLOT' ? null : 'A'.$number,
                'type_cell' => $kind === 'PARENT_PLOT' ? null : 'B'.$number]];
    }
}
