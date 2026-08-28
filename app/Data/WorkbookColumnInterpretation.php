<?php

namespace App\Data;

use App\Enums\WorkbookColumnRole;

readonly class WorkbookColumnInterpretation
{
    /**
     * @param  array<string, int|float|string|null>  $profile
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $sourceIndex,
        public string $originalHeader,
        public string $normalisedHeader,
        public WorkbookColumnRole $role,
        public ?string $subtype,
        public int $score,
        public array $profile,
        public array $reasons,
        public bool $confirmationNeeded,
        public bool $ignored,
        public bool $criticalFormulaWithoutCachedValue = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_index' => $this->sourceIndex,
            'original_header' => $this->originalHeader,
            'normalised_header' => $this->normalisedHeader,
            'semantic_role' => $this->role->value,
            'subtype' => $this->subtype,
            'score' => $this->score,
            'profile' => $this->profile,
            'reasons' => $this->reasons,
            'confirmation_needed' => $this->confirmationNeeded,
            'ignored' => $this->ignored,
            'critical_formula_without_cached_value' => $this->criticalFormulaWithoutCachedValue,
        ];
    }
}
