<?php

namespace App\Data;

readonly class SpreadsheetWorkbookData
{
    /** @param list<SpreadsheetSheetData> $sheets */
    public function __construct(
        public array $sheets,
        public string $dateSystem,
    ) {}
}
