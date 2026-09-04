<?php

namespace App\Wald\Contracts\Reasoning;

use App\Wald\Services\Reasoning\ConfidencePolicy;

interface ReasoningConstraint
{
    /** @return array{key:string,version:string,scope:string,phase?:'reduction'|'resolution'} */
    public function definition(): array;

    /** @return list<ConstraintFinding> */
    public function evaluate(array $candidates, ConfidencePolicy $policy): array;
}
