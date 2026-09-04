<?php

namespace App\Wald\Contracts;

use JsonSerializable;

final readonly class WorkbookProfile implements JsonSerializable
{
    public function __construct(private array $profile) {}

    public function toArray(): array
    {
        return $this->profile;
    }

    public function jsonSerialize(): array
    {
        return $this->profile;
    }
}
