<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\InferenceRule;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Services\Reasoning\Rules\ContextEvidenceRule;
use App\Wald\Services\Reasoning\Rules\HeaderTokenRule;
use App\Wald\Services\Reasoning\Rules\ShapeContradictionRule;
use App\Wald\Services\Reasoning\Rules\ShapeEvidenceRule;

final class RuleRegistry
{
    private array $rules = [];

    public function __construct(?array $rules = null)
    {
        $rules ??= [new HeaderTokenRule, ...array_map(fn ($metric) => new ShapeEvidenceRule($metric), ['datatype', 'uniqueness', 'cardinality', 'null_density', 'pattern_consistency']), ...array_map(fn ($metric) => new ContextEvidenceRule($metric), ['position', 'repeated_block', 'hidden_source', 'formula_derived']), new ShapeContradictionRule];
        if ($rules === [] || count($rules) > 64) {
            throw new ReasoningProblem('invalid_rule_registry');
        }
        foreach ($rules as $rule) {
            if (! $rule instanceof InferenceRule) {
                throw new ReasoningProblem('invalid_rule_registry');
            }
            $definition = $rule->definition();
            if (isset($this->rules[$definition->key])) {
                throw new ReasoningProblem('duplicate_rule_id');
            }
            if (! preg_match('/^wald\.[a-z0-9_.]+$/D', $definition->key) || ! preg_match('/^[1-9][0-9]*$/D', $definition->version)
                || ! in_array($definition->phase, [RulePhase::Evidence, RulePhase::Contradiction], true)
                || $definition->reliability < 1 || $definition->reliability > 100 || $definition->maxEvidence < 1 || $definition->maxEvidence > 8) {
                throw new ReasoningProblem('invalid_rule_registry');
            }
            $this->rules[$definition->key] = $rule;
        }
        foreach ($this->rules as $rule) {
            if (! $rule->definition()->enabled) {
                continue;
            }
            foreach ($rule->definition()->dependsOn as $dependency) {
                if (! isset($this->rules[$dependency]) || ! $this->rules[$dependency]->definition()->enabled || $this->rules[$dependency]->definition()->phase->value > $rule->definition()->phase->value) {
                    throw new ReasoningProblem('rule_dependency_missing');
                }
            }
        }
        $this->rules = $this->sort($this->rules);
    }

    private function sort(array $pending): array
    {
        $sorted = [];
        while ($pending !== []) {
            $ready = array_filter($pending, fn ($rule) => ! $rule->definition()->enabled || array_diff($rule->definition()->dependsOn, array_keys($sorted)) === []);
            if ($ready === []) {
                throw new ReasoningProblem('rule_dependency_cycle');
            }
            uasort($ready, fn ($a, $b) => [$a->definition()->phase->value, $a->definition()->priority, $a->definition()->key] <=> [$b->definition()->phase->value, $b->definition()->priority, $b->definition()->key]);
            $key = array_key_first($ready);
            $sorted[$key] = $pending[$key];
            unset($pending[$key]);
        }

        return $sorted;
    }

    public function all(): array
    {
        return array_values($this->rules);
    }

    public function snapshot(): array
    {
        return array_map(fn ($rule) => $rule->definition()->toArray(), $this->all());
    }
}
