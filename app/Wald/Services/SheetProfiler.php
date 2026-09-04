<?php

namespace App\Wald\Services;

use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;

final class SheetProfiler
{
    public function __construct(private readonly RegionDetector $regions, private readonly HeaderDetector $headers, private readonly ValueProfiler $values) {}

    public function profile(SheetObservation $sheet, AnalysisBudget $budget): array
    {
        $minRow = $minCol = PHP_INT_MAX;
        $maxRow = $maxCol = $count = $formulas = 0;
        $rowSignatures = $columnTypes = $types = $formulaSamples = [];
        foreach ($sheet->cells as $row => $cells) {
            $signature = [];
            foreach ($cells as $column => $cell) {
                $minRow = min($minRow, $row);
                $maxRow = max($maxRow, $row);
                $minCol = min($minCol, $column);
                $maxCol = max($maxCol, $column);
                $type = $this->values->type($cell);
                $types[$type] = ($types[$type] ?? 0) + 1;
                $columnTypes[$column][$type] = ($columnTypes[$column][$type] ?? 0) + 1;
                $signature[] = $column.':'.$type;
                $count++;
                if ($cell->formula !== null) {
                    $formulas++;
                    if (count($formulaSamples) < 20) {
                        $formulaSamples[] = ['cell' => SourceRange::columnLetters($column).$row, 'expression' => $cell->formula, 'cached_raw_value' => $cell->rawValue, 'cached_type' => $cell->type, 'source' => $cell->source, ...$cell->formulaMetadata];
                    }
                }
            }
            $hash = hash('sha256', implode('|', $signature));
            if (isset($rowSignatures[$hash]) || count($rowSignatures) < 64) {
                $rowSignatures[$hash] = ($rowSignatures[$hash] ?? 0) + 1;
            }
            $budget->checkpoint();
        }
        $meaningful = $count === 0 ? null : new SourceRange($minRow, $maxRow, $minCol, $maxCol);
        // Only content-bearing merges extend meaningful dimensions. They add no literal cells.
        if ($meaningful !== null) {
            foreach ($sheet->merges as $merge) {
                if (isset($sheet->cells[$merge->startRow][$merge->startColumn])) {
                    $budget->guard('columns', $merge->endColumn);
                    $meaningful = new SourceRange($meaningful->startRow, max($meaningful->endRow, $merge->endRow), $meaningful->startColumn, max($meaningful->endColumn, $merge->endColumn));
                }
            }
        }
        $profiles = [];
        foreach ($this->regions->detect($sheet, $budget) as $region) {
            $data = $region->toArray();
            $data['headers'] = $region->hypothesis === 'table' ? $this->headers->detect($sheet, $region) : null;
            $data['orientation'] = $region->hypothesis === 'table' ? $this->orientation($sheet, $region->range, $data['headers']) : 'unknown';
            $data['column_profiles'] = [];
            $data['row_profiles'] = [];
            if ($region->hypothesis === 'table') {
                $start = ($data['headers']['range']['end_row'] ?? ($region->range->startRow - 1)) + 1;
                for ($column = $region->range->startColumn; $column <= $region->range->endColumn; $column++) {
                    $data['column_profiles'][] = ['column' => $column, ...$this->values->profile($this->columnCells($sheet, $column, $start, $region->range->endRow), max(0, $region->range->endRow - $start + 1))];
                }
                // First 200 physical rows, explicitly sampled; column distributions use all cells.
                for ($row = $start; $row <= min($region->range->endRow, $start + 199); $row++) {
                    $cells = array_filter($sheet->cells[$row] ?? [], fn ($cell) => $region->range->contains($row, $cell->column));
                    $data['row_profiles'][] = ['row' => $row, ...$this->values->profile($cells, $region->range->endColumn - $region->range->startColumn + 1)];
                }
                $data['row_profile_coverage'] = ['maximum' => 200, 'sampled' => $region->range->endRow - $start + 1 > 200];
                $headerTokens = array_map(fn ($path) => mb_strtolower($path['label']), $data['headers']['paths']);
                $data['structure_signature'] = hash('sha256', json_encode([$region->range->endColumn - $region->range->startColumn + 1, $headerTokens, $data['orientation']], JSON_THROW_ON_ERROR));
                $data['evidence'][] = StructuralEvidence::make('region.orientation', $sheet->id, $region->range, ['orientation' => $data['orientation']], 'Both axes were inspected for text labels and structured values.', ['orientation_is_a_hypothesis']);
            }
            $profiles[] = $data;
            $budget->checkpoint();
        }
        $blocks = [];
        foreach ($profiles as $profile) {
            if (isset($profile['structure_signature'])) {
                $blocks[$profile['structure_signature']][] = $profile['id'];
            }
        }
        $repeated = array_filter($blocks, fn ($ids) => count($ids) > 1);
        $evidence = $meaningful === null ? [] : [StructuralEvidence::make('range.content_bounds', $sheet->id, $meaningful, ['literal_cells' => $count, 'declared_range' => $sheet->declaredRange], 'Actual content and content-bearing merges establish these bounds; empty formatting is excluded.')];
        foreach ($repeated as $signature => $ids) {
            $evidence[] = StructuralEvidence::make('region.repeated_blocks', $sheet->id, $meaningful, ['structure_signature' => $signature, 'region_ids' => $ids], 'These candidate regions share header paths, width and orientation.');
        }
        $warnings = $sheet->warnings;
        if ($meaningful === null) {
            $warnings[] = 'no_meaningful_data';
        }
        if ($blocks === []) {
            $warnings[] = 'no_candidate_table';
        }
        if ($sheet->visibility !== 'visible') {
            $warnings[] = 'hidden_region_inclusion_required';
        }
        foreach ($profiles as $profile) {
            $warnings = [...$warnings, ...$profile['warnings'], ...($profile['headers']['warnings'] ?? [])];
        }
        $columnSignatures = [];
        foreach ($columnTypes as $distribution) {
            ksort($distribution);
            $hash = hash('sha256', json_encode($distribution, JSON_THROW_ON_ERROR));
            $columnSignatures[$hash] = ($columnSignatures[$hash] ?? 0) + 1;
        }
        ksort($rowSignatures);
        ksort($columnSignatures);
        ksort($types);
        $declared = $sheet->declaredRange === null ? null : SourceRange::parse($sheet->declaredRange);

        return ['id' => $sheet->id, 'name' => $sheet->name, 'position' => $sheet->position, 'visibility' => $sheet->visibility,
            'declared_range' => $declared?->toArray(), 'meaningful_range' => $meaningful?->toArray(),
            'max_used_row' => $meaningful?->endRow ?? 0, 'max_used_column' => $meaningful?->endColumn ?? 0,
            'non_empty_cell_count' => $count, 'density' => $meaningful === null ? 0.0 : round($count / (($meaningful->endRow - $meaningful->startRow + 1) * ($meaningful->endColumn - $meaningful->startColumn + 1)), 4),
            'formula_count' => $formulas, 'formula_samples' => $formulaSamples, 'formula_sample_limit' => 20,
            'merge_count' => count($sheet->merges), 'merge_ranges' => array_map(fn ($merge) => $merge->toArray(), $sheet->merges),
            'hidden_row_count' => count($sheet->hiddenRows), 'hidden_rows' => $sheet->hiddenRows,
            'hidden_column_count' => $this->hiddenColumnCount($sheet->hiddenColumns), 'hidden_column_ranges' => $sheet->hiddenColumns,
            'value_types' => $types, 'row_pattern_counts' => $rowSignatures, 'column_pattern_counts' => $columnSignatures,
            'pattern_limitations' => ['row_signatures_first_64_distinct'],
            'blank_margins' => $meaningful === null ? null : ['leading_rows' => $meaningful->startRow - 1, 'leading_columns' => $meaningful->startColumn - 1, 'trailing_declared_rows' => max(0, ($declared?->endRow ?? $meaningful->endRow) - $meaningful->endRow), 'trailing_declared_columns' => max(0, ($declared?->endColumn ?? $meaningful->endColumn) - $meaningful->endColumn)],
            'regions' => $profiles, 'repeated_blocks' => $repeated, 'layout' => $repeated !== [] ? 'repeated_grid' : (count($blocks) > 1 ? 'multiple_regions' : 'single_or_unknown'), 'evidence' => $evidence, 'warnings' => array_values(array_unique($warnings)), 'metadata' => $sheet->metadata,
        ];
    }

    private function columnCells(SheetObservation $sheet, int $column, int $start, int $end): iterable
    {
        for ($row = $start; $row <= $end; $row++) {
            if (isset($sheet->cells[$row][$column])) {
                yield $sheet->cells[$row][$column];
            }
        }
    }

    private function orientation(SheetObservation $sheet, SourceRange $range, array $headers): string
    {
        $firstColumnText = $bodyNumeric = $body = 0;
        for ($row = $range->startRow + 1; $row <= min($range->endRow, $range->startRow + 200); $row++) {
            $cell = $sheet->cells[$row][$range->startColumn] ?? null;
            $firstColumnText += (int) ($cell !== null && $this->values->type($cell) === 'text');
            foreach ($sheet->cells[$row] ?? [] as $column => $value) {
                if ($column <= $range->startColumn || $column > $range->endColumn) {
                    continue;
                }
                $body++;
                $bodyNumeric += (int) in_array($this->values->type($value), ['integer', 'decimal', 'percentage_like', 'date_like', 'formula'], true);
            }
        }
        $height = min(200, $range->endRow - $range->startRow);
        if ($firstColumnText / max(1, $height) >= 0.8 && $bodyNumeric / max(1, $body) >= 0.7) {
            return $headers['range'] === null ? 'transposed' : 'matrix';
        }

        return $headers['range'] === null ? 'unknown' : 'vertical_records';
    }

    private function hiddenColumnCount(array $ranges): int
    {
        usort($ranges, fn ($a, $b) => $a['start'] <=> $b['start']);
        $count = $end = 0;
        foreach ($ranges as $range) {
            $count += max(0, $range['end'] - max($range['start'], $end + 1) + 1);
            $end = max($end, $range['end']);
        }

        return $count;
    }
}
