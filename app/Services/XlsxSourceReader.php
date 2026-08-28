<?php

namespace App\Services;

use App\Data\XlsxSourceReadResult;
use App\Data\XlsxSourceRow;
use App\Exceptions\InvalidSourceWorkbook;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

class XlsxSourceReader
{
    public function __construct(private readonly ManualSourceWorkbookContract $contract) {}

    public function read(string $path): XlsxSourceReadResult
    {
        $definition = $this->contract->definition();
        $this->assertSafeXlsxContainer($path);

        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->SHOULD_USE_1904_DATES = $definition['date_system'] === '1904';
        $reader = new Reader($options);
        $reader->open($path);

        try {
            $worksheetNames = [];
            $selectedSheet = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                $worksheetNames[] = $sheet->getName();
                if ($sheet->getName() === $definition['worksheet']) {
                    $selectedSheet = $sheet;
                }
            }

            if ($selectedSheet === null) {
                throw new InvalidSourceWorkbook([[
                    'code' => 'WORKSHEET_REQUIRED',
                    'message' => "Required worksheet '{$definition['worksheet']}' was not found.",
                    'blocking' => true,
                ]]);
            }

            if (! $selectedSheet->isVisible()) {
                throw new InvalidSourceWorkbook([[
                    'code' => 'WORKSHEET_HIDDEN',
                    'message' => 'The configured source worksheet must be visible.',
                    'blocking' => true,
                ]]);
            }

            $headerCells = null;
            $rows = [];
            $blankRows = 0;

            foreach ($selectedSheet->getRowIterator() as $rowNumber => $row) {
                if ($rowNumber < $definition['header_row']) {
                    continue;
                }

                if ($rowNumber === $definition['header_row']) {
                    $headerCells = $row->getCells();

                    continue;
                }

                if ($row->isEmpty()) {
                    $blankRows++;

                    continue;
                }

                $rows[] = $this->normaliseRow($rowNumber, $headerCells ?? [], $row->getCells(), $definition);
            }

            $headers = $this->headerValues($headerCells ?? []);
            $this->assertHeaders($headers, $definition);

            return new XlsxSourceReadResult(
                $definition['worksheet'],
                $worksheetNames,
                $headers,
                $rows,
                $blankRows,
            );
        } finally {
            $reader->close();
        }
    }

    private function assertSafeXlsxContainer(string $path): void
    {
        if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || ! is_file($path)) {
            throw new InvalidSourceWorkbook([[
                'code' => 'XLSX_REQUIRED',
                'message' => 'Only an XLSX workbook is accepted.',
                'blocking' => true,
            ]]);
        }

        $signature = file_get_contents($path, false, null, 0, 4);
        if ($signature !== "PK\x03\x04") {
            throw new InvalidSourceWorkbook([[
                'code' => 'INVALID_XLSX_SIGNATURE',
                'message' => 'The uploaded file is not a valid XLSX container.',
                'blocking' => true,
            ]]);
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidSourceWorkbook([[
                'code' => 'INVALID_XLSX_CONTAINER',
                'message' => 'The XLSX container could not be opened safely.',
                'blocking' => true,
            ]]);
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                throw new InvalidSourceWorkbook([[
                    'code' => 'INVALID_XLSX_STRUCTURE',
                    'message' => 'The archive does not contain the required XLSX workbook structure.',
                    'blocking' => true,
                ]]);
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = mb_strtolower((string) $zip->getNameIndex($index));
                if (str_ends_with($entry, 'vbaproject.bin') || str_contains($entry, '/embeddings/')) {
                    throw new InvalidSourceWorkbook([[
                        'code' => 'ACTIVE_CONTENT_NOT_ALLOWED',
                        'message' => 'Macros and embedded active content are not accepted.',
                        'blocking' => true,
                    ]]);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  list<Cell>  $headerCells
     * @param  list<Cell>  $cells
     * @param  array{worksheet: string, header_row: int, date_system: '1900'|'1904', headers: array<string, string|null>, product_headers: list<string>}  $definition
     */
    private function normaliseRow(int $rowNumber, array $headerCells, array $cells, array $definition): XlsxSourceRow
    {
        $headers = $this->headerValues($headerCells);
        $values = [];
        $formulaHeaders = [];

        foreach ($headers as $column => $header) {
            if ($header === '') {
                continue;
            }

            $cell = $cells[$column] ?? null;
            $values[$header] = $cell?->getValue();
            if ($cell instanceof FormulaCell) {
                $formulaHeaders[] = $header;
            }
        }

        $errors = [];
        $warnings = [];
        $value = fn (string $key) => $values[$definition['headers'][$key] ?? ''] ?? null;
        $string = fn (string $key): string => trim((string) ($value($key) ?? ''));

        foreach (['call_number', 'source_site_key', 'plot_reference', 'call_type'] as $requiredKey) {
            if ($string($requiredKey) === '') {
                $errors[] = $this->message('REQUIRED_VALUE_MISSING', "{$requiredKey} is required.");
            }
        }

        foreach ($formulaHeaders as $formulaHeader) {
            if (in_array($formulaHeader, array_filter($definition['headers']), true) || in_array($formulaHeader, $definition['product_headers'], true)) {
                $errors[] = $this->message('FORMULA_NOT_ALLOWED', "Formula cells are not accepted in source field '{$formulaHeader}'.");
            }
        }

        $completedDate = $this->dateValue($value('completed_date'), 'Completed Date', $errors, $definition['date_system']);
        $sourceUpdatedAt = $this->dateValue($value('source_updated_at'), 'Source updated timestamp', $errors, $definition['date_system'], false);
        $products = [];

        foreach ($definition['product_headers'] as $productHeader) {
            $rawQuantity = $values[$productHeader] ?? null;
            if ($rawQuantity === null || trim((string) $rawQuantity) === '') {
                $products[$productHeader] = 0.0;

                continue;
            }

            if (! is_numeric($rawQuantity) || (float) $rawQuantity < 0) {
                $errors[] = $this->message('INVALID_PRODUCT_QUANTITY', "{$productHeader} must be blank, zero or a non-negative number.");
                $products[$productHeader] = $rawQuantity;

                continue;
            }

            $products[$productHeader] = (float) $rawQuantity;
        }

        return new XlsxSourceRow(
            $rowNumber,
            $string('call_number'),
            $string('source_site_key'),
            ($name = $string('source_site_name')) === '' ? null : $name,
            $string('plot_reference'),
            $string('call_type'),
            ($stage = $string('job_stage')) === '' ? null : $stage,
            $completedDate,
            $products,
            $sourceUpdatedAt,
            $errors,
            $warnings,
        );
    }

    /** @param list<Cell> $cells
     * @return list<string>
     */
    private function headerValues(array $cells): array
    {
        return array_map(fn (Cell $cell): string => trim((string) $cell->getValue()), $cells);
    }

    /** @param list<string> $headers
     * @param  array{worksheet: string, header_row: int, date_system: '1900'|'1904', headers: array<string, string|null>, product_headers: list<string>}  $definition
     */
    private function assertHeaders(array $headers, array $definition): void
    {
        $nonBlank = array_values(array_filter($headers, fn (string $header): bool => $header !== ''));
        $duplicates = collect($nonBlank)->countBy()->filter(fn (int $count): bool => $count > 1)->keys()->all();
        $expected = collect($definition['headers'])->filter()->merge($definition['product_headers'])->uniqueStrict()->values();
        $missing = $expected->reject(fn (string $header): bool => in_array($header, $nonBlank, true))->all();
        $errors = [];

        if ($duplicates !== []) {
            $errors[] = $this->message('DUPLICATE_HEADERS', 'Duplicate headers: '.implode(', ', $duplicates).'.');
        }

        if ($missing !== []) {
            $errors[] = $this->message('REQUIRED_HEADERS_MISSING', 'Missing required headers: '.implode(', ', $missing).'.');
        }

        if ($errors !== []) {
            throw new InvalidSourceWorkbook($errors);
        }
    }

    /** @param list<array{code: string, message: string, blocking: bool}> $errors */
    private function dateValue(mixed $value, string $label, array &$errors, string $dateSystem, bool $dateOnly = true): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $baseDate = $dateSystem === '1904' ? '1904-01-01' : '1899-12-30';
            $days = (int) $value;
            $seconds = (int) round(fmod((float) $value, 1) * 86400);

            return CarbonImmutable::createFromFormat('!Y-m-d', $baseDate)?->addDays($days)->addSeconds($seconds);
        }

        $formats = $dateOnly
            ? [['parse' => '!Y-m-d', 'compare' => 'Y-m-d'], ['parse' => '!d/m/Y', 'compare' => 'd/m/Y']]
            : array_map(
                fn (string $format): array => ['parse' => $format, 'compare' => $format],
                ['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'd/m/Y H:i:s'],
            );

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format['parse'], trim((string) $value));
            } catch (\Throwable) {
                $parsed = false;
            }

            if ($parsed !== false && $parsed->format($format['compare']) === trim((string) $value)) {
                return $parsed;
            }
        }

        $errors[] = $this->message('INVALID_DATE', "{$label} is not a supported date value.");

        return null;
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private function message(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message, 'blocking' => true];
    }
}
