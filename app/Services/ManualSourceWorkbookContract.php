<?php

namespace App\Services;

use App\Exceptions\SourceWorkbookContractUnavailable;

class ManualSourceWorkbookContract
{
    /** @return array{worksheet: string, header_row: int, date_system: '1900'|'1904', headers: array<string, string|null>, product_headers: list<string>} */
    public function definition(): array
    {
        /** @var array{worksheet: string|null, header_row: int, date_system: string|null, headers: array<string, string|null>, product_headers: list<string>} $definition */
        $definition = config('manual_source_import.workbook');
        $required = ['call_number', 'source_site_key', 'plot_reference', 'call_type', 'job_stage', 'completed_date'];
        $missing = collect($required)
            ->filter(fn (string $key): bool => trim((string) ($definition['headers'][$key] ?? '')) === '')
            ->values();

        if (trim((string) $definition['worksheet']) === '' || ! in_array($definition['date_system'], ['1900', '1904'], true) || $missing->isNotEmpty()) {
            throw new SourceWorkbookContractUnavailable(
                'The representative workbook is required to confirm its worksheet, Excel date system and exact headers: '.
                implode(', ', $missing->all()).'.'
            );
        }

        $coreHeaders = collect($definition['headers'])->filter()->map(fn ($header): string => trim((string) $header));
        $productHeaders = collect($definition['product_headers'])->map(fn (string $header): string => trim($header))->filter()->values();
        $unsafeProductHeaders = $productHeaders->filter(fn (string $header): bool => mb_strtolower($header) === 'site value' || $coreHeaders->containsStrict($header));
        if ($productHeaders->duplicatesStrict()->isNotEmpty() || $unsafeProductHeaders->isNotEmpty()) {
            throw new SourceWorkbookContractUnavailable('The approved product-header allow-list is ambiguous or includes a non-product source field.');
        }

        return [
            'worksheet' => trim((string) $definition['worksheet']),
            'header_row' => max(1, (int) $definition['header_row']),
            'date_system' => $definition['date_system'],
            'headers' => collect($definition['headers'])
                ->map(fn ($header) => $header === null ? null : trim((string) $header))
                ->all(),
            'product_headers' => $productHeaders->all(),
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->definition(), JSON_THROW_ON_ERROR));
    }
}
