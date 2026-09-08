<?php

namespace App\SourceImport\Semantics\Data;

use JsonSerializable;

final readonly class ProductRollup implements JsonSerializable
{
    /** @param list<SemanticResult> $items */
    public function __construct(
        public array $items,
        public ?string $totalWindows,
        public ?string $totalDoors,
        public bool $bfPresent,
        public bool $resolved,
        public DictionaryIdentity $dictionary,
        public array $reasons = [],
    ) {}

    public function jsonSerialize(): array
    {
        return ['items' => array_map(fn (SemanticResult $item) => $item->jsonSerialize(), $this->items),
            'total_windows' => $this->totalWindows, 'total_doors' => $this->totalDoors,
            'bf_present' => $this->bfPresent, 'resolved' => $this->resolved, 'reasons' => $this->reasons,
            'coverage' => 'supplied_input_only', 'absence_authority' => false,
            'dictionary' => $this->dictionary->jsonSerialize(), 'wald_core' => CoreIdentity::snapshot(),
            'ready_for_staging' => false];
    }
}
