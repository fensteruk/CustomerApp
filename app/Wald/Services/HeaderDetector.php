<?php

namespace App\Wald\Services;

use App\Wald\Contracts\CandidateRegion;
use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;

final class HeaderDetector
{
    public function __construct(private readonly ValueProfiler $values) {}

    public function detect(SheetObservation $sheet, CandidateRegion $region): array
    {
        $range = $region->range;
        $rows = [];
        for ($row = $range->startRow; $row <= min($range->endRow, $range->startRow + 5); $row++) {
            $rows[$row] = array_filter($sheet->cells[$row] ?? [], fn ($cell) => $range->contains($row, $cell->column));
        }
        $start = $range->startRow;
        $end = $start;
        $first = $rows[$start] ?? [];
        $warnings = [];
        $textRatio = $this->textRatio($first);
        if ($textRatio < 0.5) {
            return ['range' => null, 'paths' => [], 'alternatives' => [], 'warnings' => ['no_clear_header'], 'evidence' => []];
        }
        // Multirow extension needs group/merge/repetition or explicit style evidence.
        // Consecutive all-text data is otherwise ambiguous, not automatically a header.
        for ($row = $start; $row < min($start + 2, $range->endRow); $row++) {
            $current = $rows[$row] ?? [];
            $next = $rows[$row + 1] ?? [];
            $values = array_map(fn ($cell) => trim($cell->rawValue), $current);
            $repeated = count(array_unique($values)) < count($values);
            $merged = count(array_filter($sheet->merges, fn ($merge) => $merge->startRow === $row && $merge->endColumn > $merge->startColumn && $merge->startColumn <= $range->endColumn && $merge->endColumn >= $range->startColumn)) > 0;
            if ($this->textRatio($current) >= 0.75 && $this->textRatio($next) >= 0.75 && ($repeated || $merged)) {
                $end = $row + 1;
            } else {
                break;
            }
        }
        $headerRange = new SourceRange($start, $end, $range->startColumn, $range->endColumn);
        $after = $rows[$end + 1] ?? [];
        $shift = $this->textRatio($after) < $textRatio;
        $bold = count(array_filter($first, fn ($cell) => $cell->style['bold'] ?? false)) > 0;
        if (! $shift && ! $bold && $start === $end) {
            $warnings[] = 'ambiguous_header_candidates';
        }
        $paths = [];
        for ($column = $range->startColumn; $column <= $range->endColumn; $column++) {
            $parts = $refs = [];
            for ($row = $start; $row <= $end; $row++) {
                $cell = $sheet->cells[$row][$column] ?? null;
                $mergeRef = null;
                if ($cell === null) {
                    foreach ($sheet->merges as $merge) {
                        if ($merge->contains($row, $column)) {
                            $cell = $sheet->cells[$merge->startRow][$merge->startColumn] ?? null;
                            $mergeRef = $merge->address();
                            break;
                        }
                    }
                }
                if ($cell !== null && trim($cell->rawValue) !== '') {
                    $label = trim($cell->rawValue);
                    if (end($parts) !== $label) {
                        $parts[] = $label;
                    }
                    $refs[] = ['cell' => SourceRange::columnLetters($cell->column).$cell->row, 'merge_range' => $mergeRef, 'source' => $cell->source];
                }
            }
            $paths[] = ['column' => $column, 'label' => implode(' / ', $parts), 'parts' => $parts, 'source_refs' => $refs];
        }
        $evidence = [StructuralEvidence::make('header.text_density', $sheet->id, $headerRange, ['text_ratio' => round($textRatio, 4), 'row_count' => $end - $start + 1], 'Text-rich rows before records are a candidate header.')];
        if ($shift) {
            $evidence[] = StructuralEvidence::make('header.datatype_shift', $sheet->id, $headerRange, ['following_text_ratio' => round($this->textRatio($after), 4)], 'The following row has a different value-type distribution.');
        }
        if ($bold) {
            $evidence[] = StructuralEvidence::make('header.format_emphasis', $sheet->id, $headerRange, ['bold_cells' => count(array_filter($first, fn ($cell) => $cell->style['bold'] ?? false))], 'Bold cells support the header hypothesis; formatting alone does not establish meaning.');
        }
        if ($end > $start) {
            $evidence[] = StructuralEvidence::make('header.group_inheritance', $sheet->id, $headerRange, ['composed_rows' => $end - $start + 1], 'Merged or repeated parent labels form physical header paths.');
        }

        return ['range' => $headerRange->toArray(), 'paths' => $paths, 'alternatives' => $warnings === [] ? [] : [['range' => null, 'hypothesis' => 'all_rows_may_be_data']], 'warnings' => $warnings, 'evidence' => $evidence];
    }

    /** @param array<CellObservation> $cells */
    private function textRatio(array $cells): float
    {
        return count(array_filter($cells, fn ($cell) => $this->values->type($cell) === 'text')) / max(1, count($cells));
    }
}
