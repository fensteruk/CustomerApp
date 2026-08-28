<?php

namespace App\Contracts;

use App\Data\SpreadsheetWorkbookData;
use App\Data\WorkbookInterpretation;

interface SpreadsheetStructureInterpreter
{
    public function interpret(SpreadsheetWorkbookData $workbook): WorkbookInterpretation;

    public function interpretSelection(SpreadsheetWorkbookData $workbook, string $sheet, int $headerRow): WorkbookInterpretation;
}
