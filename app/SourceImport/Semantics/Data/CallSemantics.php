<?php

namespace App\SourceImport\Semantics\Data;

use App\SourceImport\Semantics\Enums\Resolution;
use InvalidArgumentException;
use JsonSerializable;

final readonly class CallSemantics implements JsonSerializable
{
    public function __construct(
        public SemanticResult $callType,
        public SemanticResult $completion,
        public ?string $service,
        public ?bool $completed,
        public Resolution $resolution,
        public array $reasons,
    ) {
        $knownService = $callType->concept === 'call_type' && $callType->isResolved()
            && is_string($callType->value) && $service === $callType->value;
        $knownCompletion = $completion->concept === 'completion' && $completion->isResolved()
            && is_bool($completion->value) && $completed === $completion->value;
        if (($service !== null && ! $knownService)
            || ($completed !== null && (! $knownService || ! $knownCompletion || $resolution !== Resolution::Resolved))
            || ($resolution === Resolution::Resolved && (! $knownService || ! $knownCompletion))
            || $callType->dictionary != $completion->dictionary) {
            throw new InvalidArgumentException('inconsistent_call_semantics');
        }
    }

    public function jsonSerialize(): array
    {
        return ['call_type' => $this->callType->jsonSerialize(), 'completion' => $this->completion->jsonSerialize(),
            'service' => $this->service, 'completed' => $this->completed,
            'resolution' => $this->resolution->value, 'reasons' => $this->reasons,
            'completion_date' => null, 'ready_for_staging' => false];
    }
}
