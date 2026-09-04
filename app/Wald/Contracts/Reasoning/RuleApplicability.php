<?php

namespace App\Wald\Contracts\Reasoning;

final readonly class RuleApplicability
{
    private function __construct(public string $state, public ?string $reason) {}

    public static function applicable(): self
    {
        return new self('applicable', null);
    }

    public static function notApplicable(string $reason): self
    {
        return new self('not_applicable', $reason);
    }

    public static function cannotEvaluate(string $reason): self
    {
        return new self('cannot_evaluate', $reason);
    }
}
