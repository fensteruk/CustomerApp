<?php

namespace App\Wald\Contracts\Reasoning;

use App\Wald\Services\Reasoning\ReasoningProblem;

final readonly class ConstraintFinding
{
    public function __construct(public string $ruleKey, public string $version, public string $outcome, public array $candidateIds, public string $reason, public int $adjustment = 0)
    {
        if (! in_array($outcome, ['pass', 'contradiction', 'adjustment', 'clarification', 'rejected'], true) || $adjustment > 0 || $adjustment < -100 || ($outcome !== 'adjustment' && $adjustment !== 0)) {
            throw new ReasoningProblem('invalid_constraint_result');
        }
    }

    public function toArray(): array
    {
        return ['rule_key' => $this->ruleKey, 'rule_version' => $this->version, 'outcome' => $this->outcome, 'candidate_ids' => $this->candidateIds, 'reason' => $this->reason, 'adjustment' => $this->adjustment];
    }
}
