<?php

namespace App\Data;

readonly class WorkbookInterpretation
{
    /**
     * @param  list<WorkbookSheetInterpretation>  $sheets
     * @param  list<array{code: string, message: string, blocking: bool}>  $issues
     */
    public function __construct(
        public ?string $selectedSheet,
        public ?int $headerRow,
        public int $overallConfidence,
        public array $sheets,
        public array $issues,
        public bool $confirmationNeeded,
        public ?string $structuralFingerprint,
        public ?array $profileMatch = null,
        public string $dateSystem = '1900',
    ) {}

    public function selected(): ?WorkbookSheetInterpretation
    {
        foreach ($this->sheets as $sheet) {
            if ($sheet->sheet === $this->selectedSheet) {
                return $sheet;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'selected_sheet' => $this->selectedSheet,
            'header_row' => $this->headerRow,
            'overall_confidence' => $this->overallConfidence,
            'confirmation_needed' => $this->confirmationNeeded,
            'structural_fingerprint' => $this->structuralFingerprint,
            'date_system' => $this->dateSystem,
            'profile_match' => $this->profileMatch,
            'issues' => $this->issues,
            'sheets' => array_map(fn (WorkbookSheetInterpretation $sheet): array => $sheet->toArray(), $this->sheets),
        ];
    }
}
