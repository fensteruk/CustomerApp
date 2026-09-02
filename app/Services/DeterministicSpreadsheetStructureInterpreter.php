<?php

namespace App\Services;

use App\Contracts\SpreadsheetStructureInterpreter;
use App\Data\SpreadsheetCellData;
use App\Data\SpreadsheetSheetData;
use App\Data\SpreadsheetWorkbookData;
use App\Data\WorkbookColumnInterpretation;
use App\Data\WorkbookInterpretation;
use App\Data\WorkbookSheetInterpretation;
use App\Enums\WorkbookColumnRole;
use DateTimeInterface;

class DeterministicSpreadsheetStructureInterpreter implements SpreadsheetStructureInterpreter
{
    /** @var array<string, WorkbookColumnRole>|null */
    private ?array $normalisedAliases = null;

    public function __construct(private readonly SiteAppImportDataDictionary $dictionary) {}

    public function interpret(SpreadsheetWorkbookData $workbook): WorkbookInterpretation
    {
        $sheets = array_map(fn (SpreadsheetSheetData $sheet): WorkbookSheetInterpretation => $this->interpretSheet($sheet), $workbook->sheets);
        $visible = collect($sheets)->where('visible', true)->sortByDesc('sheetScore')->values();
        $selected = $visible->first();
        $runnerUp = $visible->get(1);
        $margin = (int) config('manual_source_import.sheet_selection_margin', 10);
        $issues = [];

        if ($selected === null || $selected->headerRow === null) {
            $issues[] = $this->issue('WORKSHEET_SELECTION_REQUIRED', 'No visible worksheet has a credible tabular header.', true);
            $selected = null;
        } elseif ($runnerUp !== null && $selected->sheetScore - $runnerUp->sheetScore < $margin) {
            $issues[] = $this->issue('WORKSHEET_SELECTION_REQUIRED', 'More than one worksheet plausibly contains source data; Office Staff must choose one.', true);
        }

        if ($selected !== null) {
            $issues = [...$issues, ...$selected->issues];
        }

        $confirmationNeeded = collect($issues)->where('blocking', true)->isNotEmpty()
            || ($selected !== null && collect($selected->columns)->contains(fn (WorkbookColumnInterpretation $column): bool => $column->confirmationNeeded));
        $overall = $selected === null ? 0 : min(
            $selected->headerConfidence,
            ...collect($selected->columns)
                ->filter(fn (WorkbookColumnInterpretation $column): bool => $column->role->isCritical())
                ->pluck('score')
                ->whenEmpty(fn ($collection) => collect([0]))
                ->all(),
        );

        return new WorkbookInterpretation(
            $selected?->sheet,
            $selected?->headerRow,
            $overall,
            $sheets,
            $issues,
            $confirmationNeeded,
            $selected === null ? null : $this->fingerprint($selected),
            null,
            $workbook->dateSystem,
        );
    }

    public function interpretSelection(SpreadsheetWorkbookData $workbook, string $sheet, int $headerRow): WorkbookInterpretation
    {
        $selectedSource = collect($workbook->sheets)->first(fn (SpreadsheetSheetData $candidate): bool => $candidate->name === trim($sheet));
        if ($selectedSource === null || ! $selectedSource->visible || $headerRow < 1 || $headerRow > (int) config('manual_source_import.header_scan_rows', 30)) {
            throw new \InvalidArgumentException('The selected worksheet/header row is unavailable for interpretation.');
        }

        $sheets = array_map(
            fn (SpreadsheetSheetData $candidate): WorkbookSheetInterpretation => $candidate->name === $selectedSource->name
                ? $this->interpretSheet($candidate, $headerRow)
                : $this->interpretSheet($candidate),
            $workbook->sheets,
        );
        $selected = collect($sheets)->firstWhere('sheet', $selectedSource->name);
        if ($selected === null || $selected->headerRow === null) {
            throw new \InvalidArgumentException('The selected header row is not a usable tabular row.');
        }
        $issues = $selected->issues;
        $overall = min(
            $selected->headerConfidence,
            ...collect($selected->columns)
                ->filter(fn (WorkbookColumnInterpretation $column): bool => $column->role->isCritical())
                ->pluck('score')
                ->whenEmpty(fn ($collection) => collect([0]))
                ->all(),
        );

        return new WorkbookInterpretation(
            $selected->sheet,
            $selected->headerRow,
            $overall,
            $sheets,
            $issues,
            true,
            $this->fingerprint($selected),
            null,
            $workbook->dateSystem,
        );
    }

    private function interpretSheet(SpreadsheetSheetData $sheet, ?int $forcedHeaderRow = null): WorkbookSheetInterpretation
    {
        $candidate = $forcedHeaderRow === null
            ? $this->detectHeader($sheet)
            : $this->scoreHeaderCandidate($sheet, $forcedHeaderRow);
        if ($candidate === null) {
            return new WorkbookSheetInterpretation($sheet->name, $sheet->visible, null, 0, 0, $sheet->meaningfulRowCount, [], [
                $this->issue('HEADER_ROW_REQUIRED', 'No credible header row was detected in the inspected rows.', true),
            ]);
        }

        [$headerRow, $headerConfidence] = $candidate;
        $headerCells = $sheet->sampleRows[$headerRow];
        $maxColumns = max(
            count($headerCells),
            ...collect($sheet->sampleRows)->filter(fn ($row, int $number): bool => $number > $headerRow)->map(fn (array $row): int => count($row))->all(),
        );
        $columns = [];

        for ($index = 0; $index < $maxColumns; $index++) {
            $header = trim($this->stringValue($headerCells[$index]->value ?? ''));
            $profile = $this->profileColumn($sheet, $headerRow, $index);
            $columns[] = $this->classifyColumn($index + 1, $header, $profile);
        }

        $issues = [];
        foreach ([WorkbookColumnRole::CallNumber, WorkbookColumnRole::SiteName, WorkbookColumnRole::PlotReference, WorkbookColumnRole::CallType] as $criticalRole) {
            $matches = collect($columns)->where('role', $criticalRole)->values();
            if ($matches->isEmpty()) {
                $issues[] = $this->issue('CRITICAL_MAPPING_REQUIRED', "{$criticalRole->value} must be mapped before import.", true);
            } elseif ($matches->count() > 1) {
                $issues[] = $this->issue('AMBIGUOUS_CRITICAL_MAPPING', "More than one column is a candidate for {$criticalRole->value}.", true);
                foreach ($matches as $match) {
                    $columns[$match->sourceIndex - 1] = $this->withConfirmation($match, true, 'Competing critical-field candidate requires Office confirmation.');
                }
            }
        }

        if ($headerConfidence < (int) config('manual_source_import.confirmation_mapping_score', 75)) {
            $issues[] = $this->issue('HEADER_CONFIRMATION_REQUIRED', 'The detected header row has low confidence and must be confirmed.', true);
        } elseif ($headerConfidence < (int) config('manual_source_import.auto_mapping_score', 95)) {
            $issues[] = $this->issue('HEADER_CONFIRMATION_REQUIRED', 'The detected header row should be confirmed by Office Staff.', true);
        }

        foreach ($columns as $column) {
            if ($column->criticalFormulaWithoutCachedValue) {
                $issues[] = $this->issue('CRITICAL_FORMULA_VALUE_MISSING', "{$column->originalHeader} contains a formula without a usable cached value.", true);
            }
            if ($this->dictionary->isUnconfirmedHeader($column->originalHeader)) {
                $issues[] = $this->issue(
                    'UNCONFIRMED_SOURCE_FIELD',
                    $this->dictionary->unconfirmedHeaderReason($column->originalHeader) ?? 'This source field has no approved Portal meaning.',
                    false,
                );
            }
        }

        $criticalCount = collect($columns)->filter(fn (WorkbookColumnInterpretation $column): bool => $column->role->isCritical())->count();
        $sheetScore = min(100, (int) round(($headerConfidence * .7) + ($criticalCount * 6) + min(6, log(max(1, $sheet->meaningfulRowCount) + 1, 2))));

        return new WorkbookSheetInterpretation(
            $sheet->name,
            $sheet->visible,
            $headerRow,
            $headerConfidence,
            $sheetScore,
            $sheet->meaningfulRowCount,
            $columns,
            $issues,
        );
    }

    /** @return array{int, int}|null */
    private function detectHeader(SpreadsheetSheetData $sheet): ?array
    {
        $limit = (int) config('manual_source_import.header_scan_rows', 30);
        $candidates = [];

        foreach ($sheet->sampleRows as $rowNumber => $cells) {
            if ($rowNumber > $limit) {
                break;
            }

            $candidate = $this->scoreHeaderCandidate($sheet, $rowNumber);
            if ($candidate !== null) {
                $candidates[] = [...$candidate, $this->recognisedRoleCount($cells)];
            }
        }

        usort($candidates, fn (array $left, array $right): int => [$right[1], $right[2], -$right[0]] <=> [$left[1], $left[2], -$left[0]]);

        return isset($candidates[0])
            ? [$candidates[0][0], $candidates[0][1]]
            : null;
    }

    /** @return array{int, int}|null */
    private function scoreHeaderCandidate(SpreadsheetSheetData $sheet, int $rowNumber): ?array
    {
        $cells = $sheet->sampleRows[$rowNumber] ?? null;
        if ($cells === null) {
            return null;
        }

        $values = array_map(fn (SpreadsheetCellData $cell): string => trim($this->stringValue($cell->value)), $cells);
        $nonEmptyIndexes = array_keys(array_filter($values, fn (string $value): bool => $value !== ''));
        if (count($nonEmptyIndexes) < 2) {
            return null;
        }

        $recognisedRoles = $this->recognisedRoleCount($cells);
        $nonEmpty = array_values(array_filter($values, fn (string $value): bool => $value !== ''));
        $uniqueRatio = count(array_unique(array_map([$this, 'normaliseHeader'], $nonEmpty))) / count($nonEmpty);
        $textRatio = collect($nonEmpty)->filter(fn (string $value): bool => ! is_numeric($value))->count() / count($nonEmpty);
        $span = max($nonEmptyIndexes) - min($nonEmptyIndexes) + 1;
        $contiguous = count($nonEmptyIndexes) / max(1, $span);
        $dataSupport = $this->dataRowsBelowScore($sheet, $rowNumber, $nonEmptyIndexes);
        $score = min(100, (int) round(
            min(52, $recognisedRoles * 13)
            + min(12, count($nonEmpty) * 1.5)
            + ($uniqueRatio * 10)
            + ($textRatio * 6)
            + ($contiguous * 8)
            + ($dataSupport * 12)
        ));

        return [$rowNumber, $score];
    }

    /** @param list<SpreadsheetCellData> $cells */
    private function recognisedRoleCount(array $cells): int
    {
        return collect($cells)
            ->map(fn (SpreadsheetCellData $cell) => $this->normalisedAliases()[$this->normaliseHeader(trim($this->stringValue($cell->value)))] ?? null)
            ->filter()
            ->uniqueStrict()
            ->count();
    }

    /** @param list<int> $columnIndexes */
    private function dataRowsBelowScore(SpreadsheetSheetData $sheet, int $headerRow, array $columnIndexes): float
    {
        $rows = collect($sheet->sampleRows)->filter(fn ($row, int $number): bool => $number > $headerRow)->take(8);
        if ($rows->isEmpty()) {
            return 0;
        }

        return (float) $rows->avg(function (array $cells) use ($columnIndexes): float {
            $populated = collect($columnIndexes)->filter(function (int $index) use ($cells): bool {
                $value = $cells[$index]->value ?? null;

                return $value !== null && trim($this->stringValue($value)) !== '';
            })->count();

            return $populated / max(1, count($columnIndexes));
        });
    }

    /** @return array<string, int|float|string|null> */
    private function profileColumn(SpreadsheetSheetData $sheet, int $headerRow, int $columnIndex): array
    {
        $limit = (int) config('manual_source_import.profile_sample_rows', 500);
        $cells = collect($sheet->sampleRows)
            ->filter(fn ($row, int $number): bool => $number > $headerRow)
            ->take($limit)
            ->map(fn (array $row) => $row[$columnIndex] ?? new SpreadsheetCellData(null));
        $values = $cells->map(fn (SpreadsheetCellData $cell) => $cell->value);
        $nonEmpty = $values->filter(fn ($value): bool => $value !== null && trim($this->stringValue($value)) !== '')->values();
        $count = max(1, $nonEmpty->count());
        $numeric = $nonEmpty->filter(fn ($value): bool => is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value))));
        $integers = $numeric->filter(fn ($value): bool => (float) $value === floor((float) $value));
        $dates = $nonEmpty->filter(fn ($value): bool => $value instanceof DateTimeInterface || $this->isDateString($value));
        $booleans = $nonEmpty->filter(fn ($value): bool => is_bool($value) || in_array(mb_strtolower(trim($this->stringValue($value))), ['yes', 'no', 'y', 'n', 'true', 'false', 'complete', 'completed', '0', '1'], true));
        $strings = $nonEmpty->filter(fn ($value): bool => is_string($value) && ! is_numeric(trim($value)) && ! $this->isDateString($value));
        $normalisedValues = $nonEmpty->map(fn ($value): string => mb_strtolower(trim($value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : (string) $value)));
        $numericValues = $numeric->map(fn ($value): float => (float) $value);
        $samples = $nonEmpty->take(5)->map(fn ($value): string => mb_substr($this->stringValue($value), 0, 80))->all();

        return [
            'sample_size' => $values->count(),
            'non_empty_count' => $nonEmpty->count(),
            'non_empty_percent' => round(($nonEmpty->count() / max(1, $values->count())) * 100, 1),
            'text_percent' => round(($strings->count() / $count) * 100, 1),
            'integer_percent' => round(($integers->count() / $count) * 100, 1),
            'decimal_percent' => round((($numeric->count() - $integers->count()) / $count) * 100, 1),
            'date_percent' => round(($dates->count() / $count) * 100, 1),
            'boolean_like_percent' => round(($booleans->count() / $count) * 100, 1),
            'unique_percent' => round(($normalisedValues->unique()->count() / $count) * 100, 1),
            'repeated_percent' => round((1 - ($normalisedValues->unique()->count() / $count)) * 100, 1),
            'zero_or_blank_percent' => round((($values->filter(fn ($value): bool => $value === null || trim($this->stringValue($value)) === '' || (is_numeric($value) && (float) $value === 0.0))->count()) / max(1, $values->count())) * 100, 1),
            'negative_count' => $numericValues->filter(fn (float $value): bool => $value < 0)->count(),
            'numeric_min' => $numericValues->isEmpty() ? null : $numericValues->min(),
            'numeric_max' => $numericValues->isEmpty() ? null : $numericValues->max(),
            'average_string_length' => round((float) $nonEmpty->avg(fn ($value): int => mb_strlen($this->stringValue($value))), 1),
            'formula_count' => $cells->where('formula', true)->count(),
            'formula_without_cached_value_count' => $cells->filter(fn (SpreadsheetCellData $cell): bool => $cell->formula && ! $cell->hasCachedFormulaValue)->count(),
            'sample_values' => implode(' | ', $samples),
        ];
    }

    /** @param array<string, int|float|string|null> $profile */
    private function classifyColumn(int $sourceIndex, string $header, array $profile): WorkbookColumnInterpretation
    {
        $normalised = $this->normaliseHeader($header);
        $role = $this->normalisedAliases()[$normalised] ?? null;
        $reasons = [];
        $score = 0;
        $subtype = null;
        $ignored = false;
        $auto = (int) config('manual_source_import.auto_mapping_score', 95);
        $confirm = (int) config('manual_source_import.confirmation_mapping_score', 75);

        if ($header === '') {
            $role = WorkbookColumnRole::Ignore;
            $score = 100;
            $ignored = true;
            $reasons[] = 'Blank separator column.';
        } elseif ($role !== null) {
            $generic = in_array($normalised, ['site', 'project', 'plot', 'unit', 'type', 'value', 'completed'], true);
            $score = $generic ? 84 : 97;
            $reasons[] = $generic ? 'Recognised generic header alias.' : 'Recognised specific header alias.';
            $score += $this->profileEvidenceAdjustment($role, $profile, $reasons);
        } elseif ($this->dictionary->isUnconfirmedHeader($header)) {
            $role = WorkbookColumnRole::Unknown;
            $score = 90;
            $reasons[] = $this->dictionary->unconfirmedHeaderReason($header) ?? 'The field meaning is not confirmed.';
            $reasons[] = 'It is retained as structural evidence but cannot drive an import fact.';
        } elseif ($this->isKnownProduct($header) || $this->isProductCandidate($header, $profile)) {
            $role = WorkbookColumnRole::ProductQuantity;
            $subtype = mb_strtoupper(trim($header));
            $known = $this->isKnownProduct($header);
            $numericPercent = (float) $profile['integer_percent'] + (float) $profile['decimal_percent'];
            $validKnownProfile = $numericPercent >= 70 && (int) $profile['negative_count'] === 0;
            $score = $known ? ($validKnownProfile ? 96 : 60) : 82;
            $reasons[] = $known
                ? ($validKnownProfile ? 'Confirmed SiteApp product code with a non-negative quantity profile.' : 'Confirmed SiteApp product code has invalid or negative quantity evidence and requires correction.')
                : 'Structurally product-like code column; business meaning is unconfirmed.';
        } else {
            $role = WorkbookColumnRole::Unknown;
            $score = 25;
            $reasons[] = 'No safe alias or product-quantity evidence matched.';
        }

        $score = max(0, min(100, $score));
        if ($role === WorkbookColumnRole::CommercialValue) {
            $profile['sample_values'] = '[commercial values withheld]';
        }
        $confirmationNeeded = ($role->isCritical() || $role === WorkbookColumnRole::ProductQuantity) && $score < $auto;
        if (($role->isCritical() || $role === WorkbookColumnRole::ProductQuantity) && $score < $confirm) {
            $reasons[] = 'Score is below the manual-mapping threshold.';
        } elseif ($confirmationNeeded) {
            $reasons[] = 'Score requires Office confirmation.';
        }

        $criticalFormula = ($role->isCritical() || in_array($role, [WorkbookColumnRole::CompletionFlag, WorkbookColumnRole::CompletedDate, WorkbookColumnRole::ProductQuantity], true))
            && (int) $profile['formula_without_cached_value_count'] > 0;

        return new WorkbookColumnInterpretation(
            $sourceIndex,
            $header,
            $normalised,
            $role,
            $subtype,
            $score,
            $profile,
            $reasons,
            $confirmationNeeded,
            $ignored,
            $criticalFormula,
        );
    }

    /** @param array<string, int|float|string|null> $profile
     * @param  list<string>  $reasons
     */
    private function profileEvidenceAdjustment(WorkbookColumnRole $role, array $profile, array &$reasons): int
    {
        $adjustment = 0;
        if ($role === WorkbookColumnRole::CallNumber && (float) $profile['unique_percent'] >= 95) {
            $adjustment += 3;
            $reasons[] = 'Values are nearly unique, consistent with permanent Call No. identity.';
        }
        if ($role === WorkbookColumnRole::SiteName && (float) $profile['repeated_percent'] >= 20) {
            $adjustment += 2;
            $reasons[] = 'Repeated values are consistent with a site column.';
        }
        if ($role === WorkbookColumnRole::PlotReference && (float) $profile['unique_percent'] >= 70) {
            $adjustment += 2;
            $reasons[] = 'Mostly unique values are consistent with plot references.';
        }
        if ($role === WorkbookColumnRole::CallType) {
            $known = collect(explode(' | ', (string) $profile['sample_values']))
                ->map(fn (string $value): string => mb_strtoupper(trim($value)))
                ->filter(fn (string $value): bool => $this->dictionary->isKnownCallType($value));
            if ($known->isNotEmpty()) {
                $adjustment += 3;
                $reasons[] = 'Sample contains a confirmed workbook Call Type code.';
            }
        }
        if ($role === WorkbookColumnRole::CompletionFlag && (float) $profile['boolean_like_percent'] >= 80) {
            $adjustment += 3;
            $reasons[] = 'Values are predominantly boolean-like; this is structural evidence only.';
        }
        if (in_array($role, [WorkbookColumnRole::CompletedDate, WorkbookColumnRole::OperationalTargetDate], true) && (float) $profile['date_percent'] >= 70) {
            $adjustment += 3;
            $reasons[] = 'Values are predominantly typed or safely parseable dates.';
        }

        return $adjustment;
    }

    /** @param array<string, int|float|string|null> $profile */
    private function isProductCandidate(string $header, array $profile): bool
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,9}$/', trim($header))) {
            return false;
        }

        $numericPercent = (float) $profile['integer_percent'] + (float) $profile['decimal_percent'];
        $knownProduct = $this->isKnownProduct($header);

        return $numericPercent >= 70
            && (float) $profile['date_percent'] < 50
            && (int) $profile['negative_count'] === 0
            && ($knownProduct || (float) $profile['zero_or_blank_percent'] >= 15)
            && ! in_array($this->normaliseHeader($header), ['value', 'status', 'date', 'complete', 'completed'], true);
    }

    private function isKnownProduct(string $header): bool
    {
        return $this->dictionary->isKnownProduct($header);
    }

    /** @return array<string, WorkbookColumnRole> */
    private function normalisedAliases(): array
    {
        if ($this->normalisedAliases !== null) {
            return $this->normalisedAliases;
        }

        $aliases = [];
        foreach (config('manual_source_import.aliases', []) as $roleValue => $values) {
            $role = WorkbookColumnRole::from($roleValue);
            foreach ($values as $value) {
                $normalised = $this->normaliseHeader((string) $value);
                if (isset($aliases[$normalised]) && $aliases[$normalised] !== $role) {
                    throw new \LogicException("Spreadsheet alias '{$value}' collides across semantic roles.");
                }
                $aliases[$normalised] = $role;
            }
        }

        return $this->normalisedAliases = $aliases;
    }

    private function normaliseHeader(string $header): string
    {
        return trim((string) preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($header))));
    }

    private function isDateString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false && $date->format(str_replace('!', '', $format)) === $value) {
                return true;
            }
        }

        return false;
    }

    private function stringValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : (string) $value;
    }

    private function withConfirmation(WorkbookColumnInterpretation $column, bool $needed, string $reason): WorkbookColumnInterpretation
    {
        return new WorkbookColumnInterpretation(
            $column->sourceIndex,
            $column->originalHeader,
            $column->normalisedHeader,
            $column->role,
            $column->subtype,
            $column->score,
            $column->profile,
            [...$column->reasons, $reason],
            $needed,
            $column->ignored,
            $column->criticalFormulaWithoutCachedValue,
        );
    }

    private function fingerprint(WorkbookSheetInterpretation $sheet): string
    {
        return hash('sha256', json_encode([
            'sheet' => $this->normaliseHeader($sheet->sheet),
            'headers' => array_map(fn (WorkbookColumnInterpretation $column): string => $column->normalisedHeader, $sheet->columns),
            'types' => array_map(fn (WorkbookColumnInterpretation $column): array => [
                'text' => $column->profile['text_percent'],
                'integer' => $column->profile['integer_percent'],
                'decimal' => $column->profile['decimal_percent'],
                'date' => $column->profile['date_percent'],
                'boolean' => $column->profile['boolean_like_percent'],
            ], $sheet->columns),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private function issue(string $code, string $message, bool $blocking): array
    {
        return compact('code', 'message', 'blocking');
    }
}
