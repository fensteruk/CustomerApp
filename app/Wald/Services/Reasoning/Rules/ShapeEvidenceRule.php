<?php

namespace App\Wald\Services\Reasoning\Rules;

use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\RuleApplicability;
use App\Wald\Contracts\Reasoning\RuleContext;
use App\Wald\Contracts\Reasoning\RuleDefinition;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Services\Reasoning\GenericRuleSettings as Settings;
use App\Wald\Services\Reasoning\ReasoningProblem;

final class ShapeEvidenceRule extends ProfileRule
{
    public function __construct(private readonly string $metric)
    {
        if (! in_array($metric, ['datatype', 'uniqueness', 'cardinality', 'null_density', 'pattern_consistency'], true)) {
            throw new ReasoningProblem('invalid_rule_registry');
        }
    }

    public function definition(): RuleDefinition
    {
        return new RuleDefinition('wald.generic.'.$this->metric, '1', RulePhase::Evidence, 'Evaluate a bounded value-profile observation.', ['column.types', 'column.uniqueness', 'traits.compatibility']);
    }

    public function applicability(RuleContext $context): RuleApplicability
    {
        if (! array_key_exists($context->hypothesis->definition->key, $context->traits['compatibility'])) {
            return RuleApplicability::notApplicable('not_generic_shape');
        }
        if ($this->metric === 'uniqueness' && $context->hypothesis->definition->key !== 'identifier_like') {
            return RuleApplicability::notApplicable('not_identifier_shape');
        }
        if ($this->metric === 'cardinality' && ! in_array($context->hypothesis->definition->key, ['quantity_like', 'category_like'], true)) {
            return RuleApplicability::notApplicable('not_repeated_value_shape');
        }
        if (in_array($this->metric, ['uniqueness', 'cardinality'], true) && $context->column['uniqueness'] === null) {
            return RuleApplicability::cannotEvaluate('cardinality_summary_is_bounded');
        }

        return parent::applicability($context);
    }

    public function evaluate(RuleContext $context): array
    {
        $role = $context->hypothesis->definition->key;
        $column = $context->column;
        $traits = $context->traits;
        $weight = 0;
        $correlation = 'value_shape';
        $explanation = '';
        $observation = [];
        switch ($this->metric) {
            case 'datatype':
                $ratio = $traits['compatibility'][$role] ?? 0;
                if ($ratio >= Settings::RATIOS['compatible']) {
                    $specialised = in_array($role, ['date_like', 'free_text_like'], true)
                        || ($role === 'quantity_like' && $column['types']['decimal'] / max(1, $column['observed']) >= Settings::RATIOS['compatible']);
                    $weight = $specialised ? Settings::WEIGHTS['specialised_datatype'] : Settings::WEIGHTS['datatype'];
                }
                $explanation = round($ratio * 100).'% of observed values have a compatible shape.';
                $observation = ['compatible_ratio' => $ratio];
                break;
            case 'uniqueness':
                if ($column['uniqueness'] >= Settings::RATIOS['unique']) {
                    $weight = Settings::WEIGHTS['uniqueness'];
                }
                $correlation = 'cardinality';
                $explanation = round($column['uniqueness'] * 100).'% of non-empty values are unique.';
                $observation = ['uniqueness' => $column['uniqueness']];
                break;
            case 'cardinality':
                if ($column['observed'] >= Settings::MINIMUM_OBSERVATIONS && $column['uniqueness'] <= Settings::RATIOS['low_cardinality'] && in_array($role, ['category_like', 'quantity_like'], true) && $traits['compatibility'][$role] >= Settings::RATIOS['compatible']) {
                    $weight = Settings::WEIGHTS['cardinality'];
                }
                $correlation = 'cardinality';
                $explanation = 'Values repeat within a small observed vocabulary.';
                $observation = ['distinct_count' => $column['distinct_count'], 'observed' => $column['observed']];
                break;
            case 'null_density':
                $weight = $traits['blank_ratio'] === 0.0 ? Settings::WEIGHTS['null_density'] : 0;
                $correlation = 'presence';
                $explanation = 'All profiled positions contain a value.';
                $observation = ['blank_ratio' => $traits['blank_ratio']];
                break;
            case 'pattern_consistency':
                if ($role === 'identifier_like' && $traits['identifier_ratio'] >= Settings::RATIOS['compatible']) {
                    $weight = Settings::WEIGHTS['pattern'];
                } elseif ($role === 'quantity_like' && $column['types']['decimal'] > 0 && $traits['compatibility'][$role] >= Settings::RATIOS['compatible']) {
                    $weight = Settings::WEIGHTS['pattern'];
                } elseif ($role === 'free_text_like' && $column['text']['free_text_like']) {
                    $weight = Settings::WEIGHTS['pattern'];
                }
                $explanation = 'The observed value patterns support this shape.';
                $observation = ['identifier_ratio' => $traits['identifier_ratio'], 'text_average_length' => $column['text']['average_length']];
                break;
        }

        return $weight === 0 ? [] : [$this->evidence($context, $this->metric, 'value_distribution', $correlation, EvidenceDirection::Supporting, $weight, $explanation, $observation)];
    }
}
