<?php

namespace App\Wald\Services\Reasoning\Rules;

use App\Wald\Contracts\Reasoning\Evidence;
use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\InferenceRule;
use App\Wald\Contracts\Reasoning\RuleApplicability;
use App\Wald\Contracts\Reasoning\RuleContext;

abstract class ProfileRule implements InferenceRule
{
    public function applicability(RuleContext $context): RuleApplicability
    {
        return $context->column['observed'] > 0 ? RuleApplicability::applicable() : RuleApplicability::cannotEvaluate('no_observed_values');
    }

    protected function evidence(RuleContext $context, string $code, string $family, string $correlation, EvidenceDirection $direction, int $weight, string $explanation, array $observation = [], bool $veto = false, array $limitations = []): Evidence
    {
        return new Evidence($this->definition()->key, $this->definition()->version, $context->hypothesis->id, $direction, $weight, $family, $correlation, $code, $explanation, $observation, $context->hypothesis->target['source_refs'], $veto, $limitations);
    }
}
