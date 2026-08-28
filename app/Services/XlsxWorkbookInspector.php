<?php

namespace App\Services;

use App\Data\SpreadsheetCellData;
use App\Data\SpreadsheetSheetData;
use App\Data\SpreadsheetWorkbookData;
use App\Exceptions\InvalidSourceWorkbook;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

class XlsxWorkbookInspector
{
    public function inspect(string $path): SpreadsheetWorkbookData
    {
        $dateSystem = $this->assertSafeContainerAndDateSystem($path);
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->SHOULD_USE_1904_DATES = $dateSystem === '1904';
        $reader = new Reader($options);
        $reader->open($path);
        $sheets = [];
        $sampleLimit = max(
            (int) config('manual_source_import.header_scan_rows', 30) + 10,
            (int) config('manual_source_import.profile_sample_rows', 500),
        );

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $sampleRows = [];
                $meaningfulRows = 0;
                $blankRows = 0;

                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    if ($row->isEmpty()) {
                        $blankRows++;

                        continue;
                    }

                    $meaningfulRows++;
                    if (count($sampleRows) < $sampleLimit) {
                        $sampleRows[$rowNumber] = array_map(function ($cell): SpreadsheetCellData {
                            if ($cell instanceof FormulaCell) {
                                $computed = $cell->getComputedValue();

                                $hasCachedValue = $computed !== null && (! is_string($computed) || trim($computed) !== '');

                                return new SpreadsheetCellData($computed, true, $hasCachedValue);
                            }

                            return new SpreadsheetCellData($cell->getValue());
                        }, $row->getCells());
                    }
                }

                $sheets[] = new SpreadsheetSheetData(
                    $sheet->getName(),
                    $sheet->isVisible(),
                    $sampleRows,
                    $meaningfulRows,
                    $blankRows,
                );
            }
        } finally {
            $reader->close();
        }

        return new SpreadsheetWorkbookData($sheets, $dateSystem);
    }

    public function assertSafeContainerAndDateSystem(string $path): string
    {
        if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || ! is_file($path)) {
            throw $this->invalid('XLSX_REQUIRED', 'Only an XLSX workbook is accepted.');
        }

        if (file_get_contents($path, false, null, 0, 4) !== "PK\x03\x04") {
            throw $this->invalid('INVALID_XLSX_SIGNATURE', 'The uploaded file is not a valid XLSX container.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw $this->invalid('INVALID_XLSX_CONTAINER', 'The XLSX container could not be opened safely.');
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                throw $this->invalid('INVALID_XLSX_STRUCTURE', 'The archive does not contain the required XLSX workbook structure.');
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = mb_strtolower((string) $zip->getNameIndex($index));
                if (str_ends_with($entry, 'vbaproject.bin') || str_contains($entry, '/embeddings/')) {
                    throw $this->invalid('ACTIVE_CONTENT_NOT_ALLOWED', 'Macros and embedded active content are not accepted.');
                }
            }

            $workbookXml = (string) $zip->getFromName('xl/workbook.xml');

            return preg_match('/<workbookPr[^>]*date1904=["\'](?:1|true)["\']/i', $workbookXml) === 1 ? '1904' : '1900';
        } finally {
            $zip->close();
        }
    }

    private function invalid(string $code, string $message): InvalidSourceWorkbook
    {
        return new InvalidSourceWorkbook([[
            'code' => $code,
            'message' => $message,
            'blocking' => true,
        ]]);
    }
}
