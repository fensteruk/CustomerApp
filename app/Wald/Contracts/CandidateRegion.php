<?php

namespace App\Wald\Contracts;

final readonly class CandidateRegion
{
    public function __construct(
        public string $sheetId,
        public SourceRange $range,
        public string $hypothesis,
        public float $density,
        public array $evidence,
        public array $warnings = [],
    ) {}

    public function toArray(): array
    {
        return ['id' => $this->sheetId.':'.$this->range->address(), 'sheet_id' => $this->sheetId, 'range' => $this->range->toArray(), 'hypothesis' => $this->hypothesis, 'density' => $this->density, 'evidence' => $this->evidence, 'warnings' => $this->warnings];
    }
}
