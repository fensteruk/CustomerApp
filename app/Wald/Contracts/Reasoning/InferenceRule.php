<?php

namespace App\Wald\Contracts\Reasoning;

interface InferenceRule
{
    public function definition(): RuleDefinition;

    public function applicability(RuleContext $context): RuleApplicability;

    /** @return list<Evidence> */
    public function evaluate(RuleContext $context): array;
}
