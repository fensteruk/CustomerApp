<?php

namespace App\Wald\Services;

use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;
use App\Wald\Contracts\WorkbookSource;

final class CsvWorkbookSource implements WorkbookSource
{
    private string $contents;

    private string $delimiter;

    private array $warnings = [];

    public function __construct(string $path, private readonly AnalysisBudget $budget)
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new AnalysisProblem('unreadable_workbook');
        }
        if (str_contains($contents, "\0") || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new AnalysisProblem('unsupported_encoding');
        }
        $this->contents = str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
        if (str_starts_with($contents, 'PK') || str_starts_with(ltrim($contents), '<?xml')) {
            throw new AnalysisProblem('unsupported_format');
        }
        $scores = [];
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $widths = [];
            foreach ($this->records($delimiter, false) as $index => $record) {
                if ($index > 20) {
                    break;
                }
                if (count($record['values']) > 1) {
                    $width = count($record['values']);
                    $widths[$width] = ($widths[$width] ?? 0) + 1;
                }
            }
            $scores[$delimiter] = $widths === [] ? 0 : max($widths);
        }
        arsort($scores, SORT_NUMERIC);
        $this->delimiter = array_key_first($scores);
        if (count(array_filter($scores, fn ($value) => $value === reset($scores))) > 1) {
            $this->warnings[] = 'ambiguous_csv_delimiter';
        }
    }

    public function metadata(): array
    {
        return ['adapter' => 'wald_native_csv', 'adapter_version' => '2', 'format' => 'csv', 'delimiter' => $this->delimiter, 'encoding' => 'UTF-8',
            'capabilities' => ['physical_cells' => true, 'physical_line_spans' => true, 'formulas' => false, 'cached_values' => false, 'merges' => false, 'visibility' => false, 'basic_styles' => false, 'comments' => false], 'warnings' => $this->warnings];
    }

    public function sheets(): iterable
    {
        $cells = [];
        $width = 1;
        $last = 1;
        foreach ($this->records($this->delimiter) as $row => $record) {
            $this->budget->guard('rows', $row);
            $this->budget->guard('columns', count($record['values']));
            $last = $row;
            $width = max($width, count($record['values']));
            foreach ($record['values'] as $index => $value) {
                $value = (string) $value;
                $this->budget->cell();
                $this->budget->guard('cell_bytes', strlen($value));
                if (trim($value) === '') {
                    continue;
                }
                $cells[$row][$index + 1] = new CellObservation($row, $index + 1, $value, 'text', source: ['record' => $row, 'line_start' => $record['line_start'], 'line_end' => $record['line_end'], 'column' => $index + 1]);
            }
        }
        yield new SheetObservation('sheet-1', 'CSV', 1, 'visible', 'A1:'.SourceRange::columnLetters($width).$last, $cells, metadata: ['delimiter' => $this->delimiter, 'encoding' => 'UTF-8', 'visibility_available' => false]);
    }

    public function close(): void {}

    private function records(string $delimiter, bool $validate = true): iterable
    {
        // fgetcsv materialises a whole record before row/column guards can run.
        // Reserve conservatively for copies and native array slots up front, even
        // during delimiter probing. Counting quoted delimiters overestimates safely.
        $this->budget->reserve(strlen($this->contents) * 3 + (substr_count($this->contents, $delimiter) + 1) * 96);
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            throw new AnalysisProblem('unreadable_workbook');
        }
        try {
            fwrite($stream, $this->contents);
            rewind($stream);
            $line = 1;
            $row = 0;
            while (! feof($stream)) {
                $start = ftell($stream);
                $values = fgetcsv($stream, null, $delimiter, '"', '');
                if ($values === false) {
                    break;
                }
                $raw = substr($this->contents, $start, ftell($stream) - $start);
                // Paired/doubled quotes are accepted; an unfinished quoted record is not.
                if ($validate && substr_count($raw, '"') % 2 !== 0) {
                    throw new AnalysisProblem('invalid_csv');
                }
                $breaks = preg_match_all('/\r\n|\r|\n/', $raw);
                $end = $line + $breaks - (preg_match('/[\r\n]$/', $raw) ? 1 : 0);
                yield ++$row => ['values' => $values, 'line_start' => $line, 'line_end' => max($line, $end)];
                $line += $breaks;
                $this->budget->checkpoint();
            }
        } finally {
            fclose($stream);
        }
    }
}
