<?php

namespace App\SourceImport\Semantics\Data;

use App\SourceImport\Semantics\Enums\Resolution;
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
    ) {}

    public function jsonSerialize(): array
    {
        return ['call_type' => $this->callType->jsonSerialize(), 'completion' => $this->completion->jsonSerialize(),
            'service' => $this->service, 'completed' => $this->completed,
            'resolution' => $this->resolution->value, 'reasons' => $this->reasons,
            'completion_date' => null, 'ready_for_staging' => false];
    }
}
