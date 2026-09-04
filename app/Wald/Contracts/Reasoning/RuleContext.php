<?php

namespace App\Wald\Contracts\Reasoning;

final readonly class RuleContext
{
    public function __construct(public Hypothesis $hypothesis, public array $column, public array $structure, public array $traits) {}

    public function has(string $path): bool
    {
        $value = ['column' => $this->column, 'structure' => $this->structure, 'traits' => $this->traits];
        foreach (explode('.', $path) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return false;
            }
            $value = $value[$part];
        }

        return true;
    }
}
