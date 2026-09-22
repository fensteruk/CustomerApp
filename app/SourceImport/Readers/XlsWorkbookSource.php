<?php

namespace App\SourceImport\Readers;

use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\SheetObservation;
use App\Wald\Contracts\SourceRange;
use App\Wald\Contracts\WorkbookSource;
use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Reads legacy BIFF workbooks into Wald's neutral, physical-cell observations. */
final class XlsWorkbookSource implements WorkbookSource
{
    public const ADAPTER_VERSION = '1';

    private ?Spreadsheet $workbook = null;

    private string $dateSystem;

    public function __construct(string $path, private readonly AnalysisBudget $budget)
    {
        $reader = new Xls;
        $reader->setReadEmptyCells(false);

        try {
            if (! $reader->canRead($path)) {
                throw new AnalysisProblem('invalid_workbook');
            }
            $sheets = $reader->listWorksheetInfo($path);
            $budget->guard('sheets', count($sheets));
            $estimatedCells = 0;
            foreach ($sheets as $sheet) {
                $budget->guard('rows', (int) $sheet['totalRows']);
                $budget->guard('columns', (int) $sheet['totalColumns']);
                $estimatedCells += (int) $sheet['totalRows'] * (int) $sheet['totalColumns'];
                if ($estimatedCells > 200_000) {
                    throw new AnalysisProblem('resource_limit_exceeded', ['limit' => 'xls_estimated_cells', 'maximum' => 200_000]);
                }
                $budget->checkpoint();
            }
            if ($sheets === []) {
                throw new AnalysisProblem('invalid_workbook');
            }

            // Retain number formats so dates remain distinct from ordinary numbers.
            // No formula is evaluated; later import stages reject formula cells.
            $this->workbook = $reader->load($path);
            $budget->checkpoint();
            if ($this->workbook->hasMacros()) {
                throw new AnalysisProblem('unsafe_archive');
            }
            $budget->guard('styles', count($this->workbook->getCellXfCollection()));
            $this->dateSystem = (string) $this->workbook->getExcelCalendar();
        } catch (AnalysisProblem $exception) {
            $this->close();
            throw $exception;
        } catch (\Throwable) {
            $this->close();
            throw new AnalysisProblem('invalid_workbook');
        }
    }

    public function metadata(): array
    {
        return ['adapter' => 'wald_biff_xls', 'adapter_version' => self::ADAPTER_VERSION,
            'format' => 'xls', 'date_system' => $this->dateSystem,
            'capabilities' => ['physical_cells' => true, 'formulas' => true, 'cached_values' => true,
                'merges' => true, 'visibility' => true, 'basic_styles' => true, 'comments' => false,
                'display_rendering' => false], 'warnings' => [],
        ];
    }

    public function sheets(): iterable
    {
        if ($this->workbook === null) {
            throw new AnalysisProblem('unreadable_workbook');
        }
        $styles = [];
        foreach ($this->workbook->getWorksheetIterator() as $index => $sheet) {
            $cells = [];
            foreach ($sheet->getCoordinates(false) as $address) {
                $position = SourceRange::parse($address);
                $row = $position->startRow;
                $column = $position->startColumn;
                $this->budget->guard('rows', $row);
                $this->budget->guard('columns', $column);
                $this->budget->cell();

                $cell = $sheet->getCell($address);
                $value = $cell->getValue();
                $formula = $cell->getDataType() === DataType::TYPE_FORMULA ? ltrim((string) $value, '=') : null;
                $raw = $formula === null ? $value : $cell->getOldCalculatedValue();
                $raw = $raw instanceof RichText ? $raw->getPlainText() : $raw;
                if ($raw !== null && ! is_scalar($raw)) {
                    throw new AnalysisProblem('invalid_workbook');
                }
                $raw = is_bool($raw) ? ($raw ? '1' : '0') : (string) $raw;
                $this->budget->guard('cell_bytes', strlen($raw) + strlen($formula ?? ''));
                $styleId = $cell->getXfIndex();
                $styles[$styleId] ??= ['number_format' => $this->workbook->getCellXfByIndex($styleId)->getNumberFormat()->getFormatCode()];
                $observation = new CellObservation($row, $column, $raw, match ($cell->getDataType()) {
                    DataType::TYPE_NUMERIC => 'number', DataType::TYPE_BOOL => 'boolean',
                    DataType::TYPE_ERROR => 'error', DataType::TYPE_ISO_DATE => 'date', default => 'text',
                }, $formula, $styles[$styleId], ['cell' => $address],
                    $formula === null ? [] : ['kind' => 'normal', 'shared_index' => null, 'range' => null,
                        'cached_value_available' => $cell->getOldCalculatedValue() !== null,
                        'cached_value_freshness' => 'unknown']);
                if ($observation->hasContent()) {
                    $cells[$row][$column] = $observation;
                }
            }
            ksort($cells);
            foreach ($cells as &$rowCells) {
                ksort($rowCells);
            }
            unset($rowCells);

            $merges = [];
            foreach ($sheet->getMergeCells() as $range) {
                $merges[] = SourceRange::parse($range);
                $this->budget->guard('merges', count($merges));
            }
            $hiddenRows = [];
            foreach ($sheet->getRowDimensions() as $dimension) {
                if (! $dimension->getVisible()) {
                    $hiddenRows[] = $dimension->getRowIndex();
                }
            }
            $hiddenColumns = [];
            foreach ($sheet->getColumnDimensions() as $dimension) {
                if (! $dimension->getVisible()) {
                    $column = $dimension->getColumnNumeric();
                    $hiddenColumns[] = ['start' => $column, 'end' => $column];
                }
            }
            sort($hiddenRows);
            $this->budget->checkpoint();
            $observation = new SheetObservation('sheet-'.($index + 1), $sheet->getTitle(), $index + 1,
                $sheet->getSheetState(), $sheet->calculateWorksheetDimension(), $cells,
                $merges, $hiddenRows, $hiddenColumns, [], ['date_system' => $this->dateSystem,
                    'comments_available' => false]);
            $sheet->disconnectCells();
            gc_collect_cycles();
            gc_mem_caches();
            yield $observation;
            unset($cells);
        }
    }

    public function close(): void
    {
        $this->workbook?->disconnectWorksheets();
        $this->workbook = null;
    }
}
