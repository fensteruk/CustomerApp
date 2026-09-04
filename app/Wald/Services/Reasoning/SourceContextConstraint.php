<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\ConstraintFinding;
use App\Wald\Contracts\Reasoning\ReasoningConstraint;

final class SourceContextConstraint implements ReasoningConstraint
{
    public function definition(): array
    {
        return ['key' => 'wald.constraint.source_context', 'version' => '1', 'scope' => 'hypothesis'];
    }

    public function evaluate(array $candidates, ConfidencePolicy $policy): array
    {
        $findings = [];
        foreach ($candidates as $candidate) {
            foreach (array_keys(array_filter($candidate['confirmation_flags'])) as $reason) {
                $findings[] = new ConstraintFinding($this->definition()['key'], '1', 'clarification', [$candidate['hypothesis']['id']], $reason);
            }
        }

        return $findings;
    }
}
