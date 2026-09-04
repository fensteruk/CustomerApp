<?php

namespace App\Wald\Services\Reasoning\Rules;

use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\RuleApplicability;
use App\Wald\Contracts\Reasoning\RuleContext;
use App\Wald\Contracts\Reasoning\RuleDefinition;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Services\Reasoning\GenericRuleSettings as Settings;
use App\Wald\Services\Reasoning\ReasoningProblem;

final class ContextEvidenceRule extends ProfileRule
{
    public function __construct(private readonly string $metric)
    {
        if (! in_array($metric, ['position', 'repeated_block', 'hidden_source', 'formula_derived'], true)) {
            throw new ReasoningProblem('invalid_rule_registry');
        }
    }

    public function definition(): RuleDefinition
    {
        return new RuleDefinition('wald.generic.'.$this->metric, '1', in_array($this->metric, ['hidden_source', 'formula_derived'], true) ? RulePhase::Contradiction : RulePhase::Evidence, 'Inspect physical context without reloading cells.', ['structure.hidden', 'structure.repeated', 'column.types'], critical: $this->metric !== 'repeated_block');
    }

    public function applicability(RuleContext $context): RuleApplicability
    {
        $applies = match ($this->metric) {
            'position' => $context->structure['has_header'] && $context->structure['density'] >= Settings::RATIOS['table_density'],
            'repeated_block' => $context->structure['repeated'],
            'hidden_source' => $context->structure['hidden'],
            'formula_derived' => $context->column['types']['formula'] > 0,
        };

        return $applies ? RuleApplicability::applicable() : RuleApplicability::notApplicable('context_signal_absent');
    }

    public function evaluate(RuleContext $context): array
    {
        return match ($this->metric) {
            'position' => [$this->evidence($context, 'table_position', 'surrounding_structure', 'region_layout', EvidenceDirection::Supporting, Settings::WEIGHTS['position'], 'This column belongs to a table-shaped region with a candidate header.', ['region_id' => $context->hypothesis->target['region_id']])],
            'repeated_block' => [$this->evidence($context, 'repeated_block', 'surrounding_structure', 'region_layout', EvidenceDirection::Supporting, Settings::WEIGHTS['repeated'], 'The same relative header layout repeats in another region.', [], limitations: ['correlated_with_region_layout'])],
            'hidden_source' => [$this->evidence($context, 'hidden_inclusion_required', 'format_metadata', 'visibility', EvidenceDirection::Contradicting, Settings::WEIGHTS['hidden'], 'Hidden source content requires an explicit inclusion decision.')],
            'formula_derived' => [$this->evidence($context, 'formula_cache_unverified', 'format_metadata', 'formula_cache', EvidenceDirection::Contradicting, Settings::WEIGHTS['formula'], 'Formula values have unverified cache freshness and require confirmation.', ['formula_count' => $context->column['types']['formula']])],
        };
    }
}
