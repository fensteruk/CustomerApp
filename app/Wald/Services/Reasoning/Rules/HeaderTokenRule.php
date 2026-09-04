<?php

namespace App\Wald\Services\Reasoning\Rules;

use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\RuleApplicability;
use App\Wald\Contracts\Reasoning\RuleContext;
use App\Wald\Contracts\Reasoning\RuleDefinition;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Services\Reasoning\GenericRuleSettings as Settings;

final class HeaderTokenRule extends ProfileRule
{
    public function definition(): RuleDefinition
    {
        return new RuleDefinition('wald.generic.header_tokens', '1', RulePhase::Evidence, 'Match code-owned generic shape terminology.', ['structure.header']);
    }

    public function applicability(RuleContext $context): RuleApplicability
    {
        return $context->structure['header'] === '' ? RuleApplicability::cannotEvaluate('header_unavailable') : RuleApplicability::applicable();
    }

    public function evaluate(RuleContext $context): array
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($context->structure['header']), -1, PREG_SPLIT_NO_EMPTY);
        $matches = array_values(array_intersect($context->hypothesis->definition->headerTokens, $tokens));
        if ($matches === []) {
            return [];
        }

        return [$this->evidence($context, 'generic_header_match', 'heading', 'header_language', EvidenceDirection::Supporting, Settings::WEIGHTS['header'], 'The header contains a generic term for this shape.', ['matched_tokens' => $matches])];
    }
}
