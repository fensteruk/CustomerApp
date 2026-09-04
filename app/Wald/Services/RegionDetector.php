<?php

namespace App\Wald\Services;

use App\Wald\Contracts\CandidateRegion;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;

final class RegionDetector
{
    public function __construct(private readonly ValueProfiler $values) {}

    /** Candidate boundaries are hypotheses, never inclusion decisions. */
    public function detect(SheetObservation $sheet, AnalysisBudget $budget): array
    {
        $regions = [];
        foreach ($this->bands(array_keys($sheet->cells)) as $rows) {
            $columns = [];
            foreach ($rows as $row) {
                foreach ($sheet->cells[$row] as $column => $cell) {
                    $columns[$column] = true;
                }
            }
            $columnBands = $this->bands(array_keys($columns));
            // A blank interior column alone is weak evidence; require table-sized blocks.
            $split = count($columnBands) > 1 && min(array_map('count', $columnBands)) >= 2;
            foreach ($split ? $columnBands : [array_keys($columns)] as $cols) {
                sort($cols);
                $min = min($cols);
                $max = max($cols);
                $group = [];
                $headerSignature = null;
                $flush = function () use (&$group, &$regions, &$headerSignature, $sheet, $budget): void {
                    if ($group !== []) {
                        $regions[] = $this->region($sheet, $group, count($group) >= 2 ? 'table' : 'unknown');
                        $budget->guard('regions', count($regions));
                        $group = [];
                        $headerSignature = null;
                    }
                };
                foreach ($rows as $rowOffset => $row) {
                    $cells = array_filter($sheet->cells[$row], fn ($cell) => $cell->column >= $min && $cell->column <= $max);
                    if ($cells === []) {
                        $flush();

                        continue;
                    }
                    $first = trim(reset($cells)->rawValue);
                    $footer = (bool) preg_match('/^(total|subtotal|notes?|legend|signature|prepared by|printed on)\b/i', $first);
                    // A moved Notes column can be the first heading of a real table.
                    // Require multi-column text headings followed by a datatype shift;
                    // the word alone must not discard the header as footer metadata.
                    if ($footer && count($cells) >= 2 && count(array_filter($cells, fn ($c) => $this->values->type($c) === 'text')) === count($cells)) {
                        $following = array_filter($sheet->cells[$rows[$rowOffset + 1] ?? -1] ?? [], fn ($c) => $c->column >= $min && $c->column <= $max);
                        if (count($following) >= 2 && array_filter($following, fn ($c) => $this->values->type($c) !== 'text') !== []) {
                            $footer = false;
                        }
                    }
                    $sparseHeading = count($cells) === 1 && $this->values->type(reset($cells)) === 'text';
                    if ($footer || $sparseHeading) {
                        $hadData = $group !== [] || $regions !== [];
                        $flush();
                        $regions[] = $this->region($sheet, [$row => $cells], $footer ? 'footer' : ($hadData ? 'section_heading' : 'title'));
                        $budget->guard('regions', count($regions));

                        continue;
                    }
                    $signature = implode('|', array_map(fn ($cell) => mb_strtolower(trim($cell->rawValue)), $cells));
                    if (count($group) >= 2 && $signature === $headerSignature) {
                        $flush();
                    }
                    if ($group === []) {
                        $headerSignature = $signature;
                    }
                    $group[$row] = $cells;
                }
                $flush();
            }
        }
        usort($regions, fn ($a, $b) => [$a->range->startRow, $a->range->startColumn] <=> [$b->range->startRow, $b->range->startColumn]);

        return $regions;
    }

    public function bands(array $positions): array
    {
        sort($positions, SORT_NUMERIC);
        $bands = [];
        $last = null;
        foreach ($positions as $position) {
            if ($last === null || $position > $last + 1) {
                $bands[] = [];
            }
            $bands[array_key_last($bands)][] = $position;
            $last = $position;
        }

        return $bands;
    }

    private function region(SheetObservation $sheet, array $rows, string $type): CandidateRegion
    {
        $minColumn = PHP_INT_MAX;
        $maxColumn = 0;
        $count = 0;
        foreach ($rows as $cells) {
            $minColumn = min($minColumn, min(array_keys($cells)));
            $maxColumn = max($maxColumn, max(array_keys($cells)));
            $count += count($cells);
        }
        $range = new SourceRange(min(array_keys($rows)), max(array_keys($rows)), $minColumn, $maxColumn);
        $density = round($count / (($range->endRow - $range->startRow + 1) * ($range->endColumn - $range->startColumn + 1)), 4);

        $hidden = $sheet->visibility !== 'visible'
            || count(array_filter($sheet->hiddenRows, fn ($row) => $row >= $range->startRow && $row <= $range->endRow)) > 0
            || count(array_filter($sheet->hiddenColumns, fn ($columns) => $columns['start'] <= $range->endColumn && $columns['end'] >= $range->startColumn)) > 0;

        return new CandidateRegion($sheet->id, $range, $type, $density, [StructuralEvidence::make('region.occupancy', $sheet->id, $range, ['non_empty_cells' => $count, 'density' => $density, 'hypothesis' => $type], 'Physical occupancy, separators and row patterns suggest this region.')], $hidden ? ['hidden_region_inclusion_required'] : []);
    }
}
