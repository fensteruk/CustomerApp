<?php

namespace App\Services;

use App\Data\WorkbookInterpretation;
use App\Enums\WorkbookColumnRole;
use App\Exceptions\InvalidSourceWorkbook;

class WorkbookMappingService
{
    public function __construct(private readonly WorkbookInterpretationProfileService $profiles) {}

    /** @return array{sheet: string, header_row: int, columns: list<array{source_index: int, semantic_role: string, subtype: string|null}>}|null */
    public function automatic(WorkbookInterpretation $interpretation): ?array
    {
        if ($interpretation->confirmationNeeded || $interpretation->selected() === null) {
            return null;
        }

        return $this->canonicalise($interpretation, [
            'sheet' => $interpretation->selectedSheet,
            'header_row' => $interpretation->headerRow,
            'columns' => array_map(fn ($column): array => [
                'source_index' => $column->sourceIndex,
                'semantic_role' => $column->role->value,
                'subtype' => $column->subtype,
            ], $interpretation->selected()->columns),
        ]);
    }

    /**
     * @param  array{sheet: string, header_row: int, columns: list<array{source_index: int, semantic_role: string, subtype?: string|null}>}  $mapping
     * @return array{sheet: string, header_row: int, columns: list<array{source_index: int, semantic_role: string, subtype: string|null}>}
     */
    public function canonicalise(WorkbookInterpretation $interpretation, array $mapping): array
    {
        $sheet = collect($interpretation->sheets)->firstWhere('sheet', trim($mapping['sheet']));
        $errors = [];
        if ($sheet === null || ! $sheet->visible) {
            $errors[] = $this->issue('WORKSHEET_REQUIRED', 'The confirmed worksheet is unavailable or hidden.');
        } elseif ($sheet->headerRow !== (int) $mapping['header_row']) {
            $errors[] = $this->issue('HEADER_ROW_CHANGED', 'The confirmed header row does not match the current workbook structure.');
        }

        $columnsByIndex = $sheet === null ? collect() : collect($sheet->columns)->keyBy('sourceIndex');
        $canonical = [];
        if (collect($mapping['columns'])->pluck('source_index')->duplicates()->isNotEmpty()) {
            $errors[] = $this->issue('DUPLICATE_COLUMN_MAPPING', 'A source column may be mapped only once.');
        }
        foreach ($mapping['columns'] as $column) {
            $sourceIndex = (int) $column['source_index'];
            $role = WorkbookColumnRole::tryFrom((string) $column['semantic_role']);
            $observed = $columnsByIndex->get($sourceIndex);
            if ($role === null || $observed === null) {
                $errors[] = $this->issue('INVALID_COLUMN_MAPPING', "Column {$sourceIndex} is not available for mapping.");

                continue;
            }

            if ($observed->role === WorkbookColumnRole::OperationalTargetDate
                && ! in_array($role, [WorkbookColumnRole::OperationalTargetDate, WorkbookColumnRole::Ignore], true)) {
                $errors[] = $this->issue('OPERATIONAL_DATE_SAFETY_OVERRIDE', 'Plot To Be Installed/operational target dates cannot be mapped into Portal request or agreement data.');
            }
            if ($observed->role === WorkbookColumnRole::CommercialValue
                && ! in_array($role, [WorkbookColumnRole::CommercialValue, WorkbookColumnRole::Ignore], true)) {
                $errors[] = $this->issue('COMMERCIAL_VALUE_SAFETY_OVERRIDE', 'Commercial values cannot be mapped as products or customer-visible data.');
            }

            $subtype = isset($column['subtype']) ? mb_strtoupper(trim((string) $column['subtype'])) : null;
            if ($role === WorkbookColumnRole::ProductQuantity) {
                if ($subtype === null || ! preg_match('/^[A-Z][A-Z0-9_-]{1,31}$/', $subtype)) {
                    $errors[] = $this->issue('PRODUCT_CODE_REQUIRED', "Column {$sourceIndex} requires a safe product code.");
                }
                if ((int) $observed->profile['negative_count'] > 0) {
                    $errors[] = $this->issue('NEGATIVE_PRODUCT_QUANTITY', "Column {$sourceIndex} contains a negative quantity.");
                }
            } else {
                $subtype = null;
            }

            if (($role->isCritical() || in_array($role, [WorkbookColumnRole::ProductQuantity, WorkbookColumnRole::CompletionFlag, WorkbookColumnRole::CompletedDate], true))
                && (int) $observed->profile['formula_without_cached_value_count'] > 0) {
                $errors[] = $this->issue('CRITICAL_FORMULA_VALUE_MISSING', "Column {$sourceIndex} contains a formula without a usable cached value.");
            }

            $canonical[] = [
                'source_index' => $sourceIndex,
                'semantic_role' => $role->value,
                'subtype' => $subtype,
            ];
        }

        $roles = collect($canonical)->pluck('semantic_role');
        foreach ([WorkbookColumnRole::CallNumber, WorkbookColumnRole::PlotReference, WorkbookColumnRole::CallType] as $critical) {
            if ($roles->filter(fn (string $role): bool => $role === $critical->value)->count() !== 1) {
                $errors[] = $this->issue('CRITICAL_MAPPING_REQUIRED', "Exactly one {$critical->value} column is required.");
            }
        }
        $siteCount = $roles->filter(fn (string $role): bool => in_array($role, [WorkbookColumnRole::SiteName->value, WorkbookColumnRole::SiteExternalId->value], true))->count();
        if ($siteCount < 1 || $roles->filter(fn (string $role): bool => $role === WorkbookColumnRole::SiteExternalId->value)->count() > 1 || $roles->filter(fn (string $role): bool => $role === WorkbookColumnRole::SiteName->value)->count() > 1) {
            $errors[] = $this->issue('CRITICAL_MAPPING_REQUIRED', 'A single Site Name or Site External ID mapping is required; at most one of each is allowed.');
        }

        foreach ([WorkbookColumnRole::CompletionFlag, WorkbookColumnRole::CompletedDate, WorkbookColumnRole::OperationalTargetDate, WorkbookColumnRole::CommercialValue] as $singleton) {
            if ($roles->filter(fn (string $role): bool => $role === $singleton->value)->count() > 1) {
                $errors[] = $this->issue('AMBIGUOUS_COLUMN_MAPPING', "Only one {$singleton->value} column may be mapped.");
            }
        }
        if (collect($canonical)->where('semantic_role', WorkbookColumnRole::ProductQuantity->value)->pluck('subtype')->duplicates()->isNotEmpty()) {
            $errors[] = $this->issue('DUPLICATE_PRODUCT_CODE', 'Each mapped product code must be unique.');
        }

        if ($errors !== []) {
            throw new InvalidSourceWorkbook($errors);
        }

        return [
            'sheet' => trim($mapping['sheet']),
            'header_row' => (int) $mapping['header_row'],
            'columns' => collect($canonical)->sortBy('source_index')->values()->all(),
        ];
    }

    /** @param array<string, mixed> $mapping */
    public function fingerprint(WorkbookInterpretation $interpretation, array $mapping): string
    {
        $sheet = collect($interpretation->sheets)->firstWhere('sheet', $mapping['sheet']);

        return hash('sha256', json_encode([
            'structure' => $sheet === null ? null : $this->profiles->fingerprintForSheet($sheet),
            'mapping' => $mapping,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private function issue(string $code, string $message): array
    {
        return compact('code', 'message') + ['blocking' => true];
    }
}
