<?php

namespace App\Actions\CallOff;

class CallOffEligibility
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $reasons = [],
    ) {}

    public static function allowed(): self
    {
        return new self(true);
    }

    public static function denied(string $reason): self
    {
        return new self(false, [$reason]);
    }
}
