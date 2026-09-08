<?php

namespace App\SourceImport\Semantics\Data;

use App\SourceImport\Semantics\Dictionary\Quantity;
use App\SourceImport\Semantics\Enums\Classification;
use InvalidArgumentException;
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
    ) {
        $totals = ['WINDOWS' => 0, 'DOORS' => 0];
        $seen = [];
        $valid = $reasons === [];
        $observedBf = false;
        foreach ($items as $item) {
            if (! $item instanceof SemanticResult || $item->concept !== 'product_quantity'
                || $item->dictionary != $dictionary) {
                throw new InvalidArgumentException('invalid_rollup_item');
            }
            $valid = $valid && $item->isResolved() && ! isset($seen[$item->lookupValue]);
            $seen[$item->lookupValue] = true;
            if (! $item->isResolved() || $item->classification === Classification::Ignored) {
                continue;
            }
            $units = Quantity::units($item->value);
            $group = $item->match['group'] ?? null;
            if ($units === null || ! isset($totals[$group])) {
                throw new InvalidArgumentException('invalid_rollup_item');
            }
            $observedBf = $observedBf || ($item->lookupValue === 'BF' && $units > 0);
            if ($units > Quantity::MAX_UNITS - $totals[$group]) {
                $valid = false;
            } else {
                $totals[$group] += $units;
            }
        }
        if ($bfPresent !== $observedBf
            || (! $resolved && ($totalWindows !== null || $totalDoors !== null))
            || ($resolved && (! $valid || $totalWindows !== Quantity::decimal($totals['WINDOWS'])
                || $totalDoors !== Quantity::decimal($totals['DOORS'])))) {
            throw new InvalidArgumentException('inconsistent_product_rollup');
        }
    }

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
