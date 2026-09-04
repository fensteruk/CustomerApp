<?php

namespace App\Wald\Contracts\Reasoning;

use JsonSerializable;

final readonly class ReasoningResult implements JsonSerializable
{
    public function __construct(private array $result) {}

    public function toArray(): array
    {
        return $this->result;
    }

    public function jsonSerialize(): array
    {
        return $this->result;
    }
}
