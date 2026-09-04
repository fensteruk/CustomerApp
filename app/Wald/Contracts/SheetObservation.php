<?php

namespace App\Wald\Contracts;

final readonly class SheetObservation
{
    /** @param array<int, array<int, CellObservation>> $cells Physical, one-based coordinates. */
    public function __construct(
        public string $id,
        public string $name,
        public int $position,
        public string $visibility,
        public ?string $declaredRange,
        public array $cells,
        public array $merges = [],
        public array $hiddenRows = [],
        public array $hiddenColumns = [],
        public array $warnings = [],
        public array $metadata = [],
    ) {}
}
