<?php

namespace App\Wald\Services\Reasoning\Rules;

use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\RuleApplicability;
use App\Wald\Contracts\Reasoning\RuleContext;
use App\Wald\Contracts\Reasoning\RuleDefinition;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Services\Reasoning\GenericRuleSettings as Settings;

final class ShapeContradictionRule extends ProfileRule
{
    public function definition(): RuleDefinition
    {
        return new RuleDefinition('wald.generic.shape_contradictions', '1', RulePhase::Contradiction, 'Preserve decisive counter-evidence to shape hypotheses.', ['traits.compatibility', 'column.uniqueness', 'column.dates'], maxEvidence: 6);
    }

    public function applicability(RuleContext $context): RuleApplicability
    {
        return array_key_exists($context->hypothesis->definition->key, $context->traits['compatibility'])
            ? parent::applicability($context)
            : RuleApplicability::notApplicable('not_generic_shape');
    }

    public function evaluate(RuleContext $context): array
    {
        $role = $context->hypothesis->definition->key;
        $column = $context->column;
        $traits = $context->traits;
        $evidence = [];
        $add = function (string $code, string $correlation, string $text, bool $veto = false) use (&$evidence, $context): void {
            $evidence[] = $this->evidence($context, $code, 'value_distribution', $correlation, EvidenceDirection::Contradicting, Settings::WEIGHTS['contradiction'], $text, [], $veto);
        };
        if (($traits['compatibility'][$role] ?? 0) <= Settings::RATIOS['incompatible'] && $column['types']['formula'] === 0) {
            $add('incompatible_values', 'value_shape', 'The observed values contradict this proposed shape.', true);
        }
        if ($role === 'identifier_like' && $column['uniqueness'] !== null && $column['uniqueness'] < Settings::RATIOS['unique']) {
            $add('identifier_repetition', 'cardinality', 'Repeated values contradict a primary identifier interpretation.', true);
        }
        if ($role === 'quantity_like' && $traits['percentage_ratio'] > Settings::RATIOS['incompatible']) {
            $add('percentage_not_quantity', 'value_shape', 'Percentage-shaped values do not establish quantities.', true);
        }
        if ($role === 'quantity_like' && $column['uniqueness'] !== null && $column['uniqueness'] >= Settings::RATIOS['unique'] && $column['types']['integer'] === $column['observed']) {
            $add('numeric_identity_alternative', 'cardinality', 'Unique integer values may be identifiers rather than quantities.');
        }
        if ($role === 'date_like' && $column['dates']['ambiguous']) {
            $add('date_ambiguity', 'date_parse', 'Date-like values have unresolved ordering or precision.');
        }
        if ($traits['blank_ratio'] >= Settings::RATIOS['mostly_blank']) {
            $add('mostly_blank', 'presence', 'At least half of the profiled positions are blank.');
        }

        return $evidence;
    }
}
