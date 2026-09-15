<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;

/** CustomerApp-specific logical composition over immutable physical observations. */
final class CompositeTableDetector
{
    private const REQUIRED_ROLES = ['call_reference', 'plot_reference', 'call_type', 'completion'];

    public function detect(SheetObservation $sheet): array
    {
        if ($sheet->visibility !== 'visible' || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
            return [];
        }

        $dictionary = new CustomerAppDictionary;
        $candidates = [];

        foreach (array_keys($sheet->cells) as $headerRow) {
            $headerCells = $this->contentCells($sheet->cells[$headerRow] ?? []);
            if (count($headerCells) < 5) {
                continue;
            }

            $columns = [];
            $rolesByColumn = [];
            foreach ($headerCells as $column => $cell) {
                $role = $dictionary->field($cell->rawValue)->value;
                if ($role === null) {
                    $product = $dictionary->product($cell->rawValue, '0');
                    if ($product->isResolved()) {
                        $role = 'quantity:'.$product->lookupValue;
                    }
                }
                $rolesByColumn[$column] = $role;
            }

            $allRoles = array_fill_keys(array_filter(array_values($rolesByColumn)), true);
            $hasSite = isset($allRoles['source_site_identity']) || isset($allRoles['transitional_site_clue']);
            if (! $hasSite || array_diff(self::REQUIRED_ROLES, array_keys($allRoles)) !== []) {
                continue;
            }

            $bands = $this->complementaryFragments($headerCells, $rolesByColumn);
            if ($bands === null) {
                continue;
            }

            foreach ($headerCells as $column => $cell) {
                $fragment = $column <= max($bands[0]) ? 1 : 2;
                $columns[] = [
                    'column' => $column,
                    'header' => $cell->rawValue,
                    'role' => $rolesByColumn[$column],
                    'fragment' => $fragment,
                    'source_refs' => [[
                        'cell' => SourceRange::columnLetters($column).$headerRow,
                        'merge_range' => null,
                        'source' => $cell->source,
                    ]],
                ];
            }

            $dataStart = $this->nextContentRow($sheet, $headerRow, $bands);
            if ($dataStart === null || $dataStart > $headerRow + 3) {
                continue;
            }

            $dataEnd = $this->alignedDataEnd($sheet, $dataStart, $bands, $rolesByColumn);
            if ($dataEnd === null || $dataEnd < $dataStart) {
                continue;
            }

            $headerRanges = [];
            $dataRanges = [];
            foreach ($bands as $band) {
                $headerRanges[] = new SourceRange($headerRow, $headerRow, min($band), max($band));
                $dataRanges[] = new SourceRange($dataStart, $dataEnd, min($band), max($band));
            }
            $headerReference = implode('+', array_map(fn (SourceRange $range): string => $range->address(), $headerRanges));
            $dataReference = implode('+', array_map(fn (SourceRange $range): string => $range->address(), $dataRanges));
            $id = $sheet->id.':composite:'.hash('sha256', $headerReference.'|'.$dataReference);
            $candidates[$id] = [
                'id' => $id,
                'sheet_id' => $sheet->id,
                'canonical_reference' => $headerReference,
                'data_reference' => $dataReference,
                'header_range' => (new SourceRange($headerRow, $headerRow, min(array_merge(...$bands)), max(array_merge(...$bands))))->toArray(),
                'range' => (new SourceRange($headerRow, $dataEnd, min(array_merge(...$bands)), max(array_merge(...$bands))))->toArray(),
                'data_start_row' => $dataStart,
                'data_end_row' => $dataEnd,
                'fragments' => array_map(
                    fn (SourceRange $header, int $index): array => [
                        'id' => $index + 1,
                        'header_range' => $header->toArray(),
                        'data_range' => $dataRanges[$index]->toArray(),
                    ],
                    $headerRanges,
                    array_keys($headerRanges),
                ),
                'columns' => $columns,
                'warnings' => [],
            ];
        }

        ksort($candidates, SORT_STRING);

        return array_values($candidates);
    }

    private function nextContentRow(SheetObservation $sheet, int $headerRow, array $bands): ?int
    {
        for ($row = $headerRow + 1; $row <= $headerRow + 3; $row++) {
            if ($this->rowHasContent($sheet, $row, $bands)) {
                return $row;
            }
        }

        return null;
    }

    private function alignedDataEnd(SheetObservation $sheet, int $start, array $bands, array $rolesByColumn): ?int
    {
        $callColumn = array_search('call_reference', $rolesByColumn, true);
        $anchor = collect($bands)->first(fn (array $band): bool => in_array($callColumn, $band, true));
        if ($anchor === null) {
            return null;
        }

        $end = $start - 1;
        for ($row = $start; $this->rowHasContent($sheet, $row, [$anchor]); $row++) {
            $end = $row;
        }
        if ($end < $start) {
            return null;
        }

        return $end;
    }

    private function rowHasContent(SheetObservation $sheet, int $row, array $bands): bool
    {
        $cells = $sheet->cells[$row] ?? [];
        foreach ($bands as $band) {
            foreach ($band as $column) {
                if (isset($cells[$column]) && $cells[$column]->hasContent()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contentCells(array $cells): array
    {
        return array_filter($cells, fn ($cell): bool => $cell->hasContent());
    }

    private function complementaryFragments(array $headerCells, array $rolesByColumn): ?array
    {
        $occupied = array_keys($headerCells);
        sort($occupied, SORT_NUMERIC);
        $minimum = min($occupied);
        $maximum = max($occupied);
        $candidates = [];

        for ($leftEnd = $minimum; $leftEnd < $maximum; $leftEnd++) {
            if (! isset($headerCells[$leftEnd]) || isset($headerCells[$leftEnd + 1])) {
                continue;
            }
            $rightStart = $leftEnd + 1;
            while ($rightStart <= $maximum && ! isset($headerCells[$rightStart])) {
                $rightStart++;
            }
            if ($rightStart > $maximum || $leftEnd - $minimum < 1 || $maximum - $rightStart < 1) {
                continue;
            }

            $leftRoles = array_fill_keys(array_filter(array_values(array_filter(
                $rolesByColumn,
                fn ($role, $column): bool => $column <= $leftEnd,
                ARRAY_FILTER_USE_BOTH,
            ))), true);
            $rightRoles = array_fill_keys(array_filter(array_values(array_filter(
                $rolesByColumn,
                fn ($role, $column): bool => $column >= $rightStart,
                ARRAY_FILTER_USE_BOTH,
            ))), true);
            $identityLeft = isset($leftRoles['call_reference'], $leftRoles['plot_reference'])
                && (isset($leftRoles['source_site_identity']) || isset($leftRoles['transitional_site_clue']));
            $identityRight = isset($rightRoles['call_reference'], $rightRoles['plot_reference'])
                && (isset($rightRoles['source_site_identity']) || isset($rightRoles['transitional_site_clue']));
            $visitLeft = isset($leftRoles['call_type'], $leftRoles['completion']);
            $visitRight = isset($rightRoles['call_type'], $rightRoles['completion']);
            if (($identityLeft && $visitRight && ! $visitLeft && ! $identityRight)
                || ($identityRight && $visitLeft && ! $visitRight && ! $identityLeft)) {
                $candidates[] = [range($minimum, $leftEnd), range($rightStart, $maximum)];
            }
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }
}
