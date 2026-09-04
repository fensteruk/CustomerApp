<?php

namespace App\Wald\Services\Reasoning;

use RuntimeException;

final class ReasoningProblem extends RuntimeException
{
    public function __construct(public readonly string $problemCode)
    {
        parent::__construct('Wald could not complete this reasoning run safely.');
    }

    public function toArray(): array
    {
        return ['code' => $this->problemCode, 'message' => $this->getMessage(), 'complete' => false];
    }
}
