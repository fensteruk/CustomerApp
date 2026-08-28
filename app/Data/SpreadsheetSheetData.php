<?php

namespace App\Data;

readonly class SpreadsheetSheetData
{
    /**
     * @param  array<int, list<SpreadsheetCellData>>  $sampleRows
     */
    public function __construct(
        public string $name,
        public bool $visible,
        public array $sampleRows,
        public int $meaningfulRowCount,
        public int $blankRowCount,
    ) {}
}
