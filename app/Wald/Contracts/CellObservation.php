<?php

namespace App\Wald\Contracts;

final readonly class CellObservation
{
    public function __construct(
        public int $row,
        public int $column,
        public string $rawValue,
        public string $type = 'text',
        public ?string $formula = null,
        public array $style = [],
        public array $source = [],
        public array $formulaMetadata = [],
    ) {}

    public function hasContent(): bool
    {
        return trim($this->rawValue) !== '' || $this->formula !== null;
    }
}
