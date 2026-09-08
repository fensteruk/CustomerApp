<?php

namespace App\SourceImport\Semantics\Data;

use App\SourceImport\Semantics\Enums\Classification;
use App\SourceImport\Semantics\Enums\Resolution;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SemanticResult implements JsonSerializable
{
    public function __construct(
        public string $concept,
        public string|int|float|bool|null $rawValue,
        public ?string $lookupValue,
        public Classification $classification,
        public Resolution $resolution,
        public DictionaryIdentity $dictionary,
        public ?array $match = null,
        public string|int|bool|null $value = null,
        public array $suggestions = [],
        public array $reasons = [],
        public array $evidence = [],
    ) {
        $known = in_array($classification, [Classification::Confirmed, Classification::Ignored], true);
        if (($resolution === Resolution::Resolved && ! $known)
            || ($value !== null && (! $known || $resolution === Resolution::Blocked))) {
            throw new InvalidArgumentException('inconsistent_semantic_result');
        }
    }

    public function isResolved(): bool
    {
        return $this->resolution === Resolution::Resolved
            && in_array($this->classification, [Classification::Confirmed, Classification::Ignored], true);
    }

    public function jsonSerialize(): array
    {
        // JSON has no non-finite numbers. Keep a lossless tagged representation.
        $raw = is_float($this->rawValue) && ! is_finite($this->rawValue)
            ? ['type' => 'non_finite_float', 'value' => (string) $this->rawValue] : $this->rawValue;

        return ['concept' => $this->concept, 'raw_value' => $raw,
            'lookup_value' => $this->lookupValue, 'classification' => $this->classification->value,
            'resolution' => $this->resolution->value, 'match' => $this->match, 'value' => $this->value,
            'suggestions' => $this->suggestions, 'reasons' => $this->reasons, 'evidence' => $this->evidence,
            'dictionary' => $this->dictionary->jsonSerialize(), 'wald_core' => CoreIdentity::snapshot(),
            'clarification_required' => ! $this->isResolved(),
            // Semantic resolution never supplies the later staging/commit prerequisites.
            'ready_for_staging' => false];
    }
}
