<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\HypothesisDefinition;

final class HypothesisCatalog
{
    private array $definitions;

    /** Definitions are code-owned; no submitted class names or executable configuration. */
    public function __construct(?array $definitions = null)
    {
        $this->definitions = $definitions ?? [
            new HypothesisDefinition('identifier_like', 'Identifier-like field', ['id', 'identifier', 'reference', 'ref', 'code'], exclusiveWithinRegion: 'primary_identifier_like'),
            new HypothesisDefinition('date_like', 'Date-like field', ['date', 'when', 'day']),
            new HypothesisDefinition('quantity_like', 'Quantity-like field', ['quantity', 'qty', 'amount', 'count', 'units']),
            new HypothesisDefinition('category_like', 'Category-like field', ['category', 'group', 'type', 'class']),
            new HypothesisDefinition('free_text_like', 'Free-text-like field', ['notes', 'note', 'description', 'comments', 'text']),
        ];
        $seen = [];
        if ($this->definitions === [] || count($this->definitions) > 64) {
            throw new ReasoningProblem('invalid_hypothesis_catalog');
        }
        foreach ($this->definitions as $definition) {
            if (! $definition instanceof HypothesisDefinition || isset($seen[$definition->key]) || ! preg_match('/^[a-z][a-z0-9_.]+$/D', $definition->key) || $definition->version === '') {
                throw new ReasoningProblem('invalid_hypothesis_catalog');
            }
            $seen[$definition->key] = true;
        }
        usort($this->definitions, fn ($a, $b) => strcmp($a->key, $b->key));
    }

    public function all(): array
    {
        return $this->definitions;
    }

    public function snapshot(): array
    {
        return array_map(fn ($definition) => $definition->toArray(), $this->definitions);
    }

    public function forTraits(array $traits): array
    {
        $eligible = array_values(array_filter($this->definitions, fn ($definition) => $definition->eligibleTraits === [] || count(array_filter($definition->eligibleTraits, fn ($trait) => ($traits[$trait] ?? false) === true)) > 0));
        if (count($eligible) > 5) {
            throw new ReasoningProblem('candidate_limit_exceeded');
        }

        return $eligible;
    }
}
