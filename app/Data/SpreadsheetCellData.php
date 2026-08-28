<?php

namespace App\Data;

readonly class SpreadsheetCellData
{
    public function __construct(
        public mixed $value,
        public bool $formula = false,
        public bool $hasCachedFormulaValue = false,
    ) {}
}
