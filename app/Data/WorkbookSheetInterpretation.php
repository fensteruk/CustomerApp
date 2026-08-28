<?php

namespace App\Data;

readonly class WorkbookSheetInterpretation
{
    /**
     * @param  list<WorkbookColumnInterpretation>  $columns
     * @param  list<array{code: string, message: string, blocking: bool}>  $issues
     */
    public function __construct(
        public string $sheet,
        public bool $visible,
        public ?int $headerRow,
        public int $headerConfidence,
        public int $sheetScore,
        public int $meaningfulRowCount,
        public array $columns,
        public array $issues,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sheet' => $this->sheet,
            'visible' => $this->visible,
            'header_row' => $this->headerRow,
            'header_confidence' => $this->headerConfidence,
            'sheet_score' => $this->sheetScore,
            'meaningful_row_count' => $this->meaningfulRowCount,
            'columns' => array_map(fn (WorkbookColumnInterpretation $column): array => $column->toArray(), $this->columns),
            'issues' => $this->issues,
        ];
    }
}
