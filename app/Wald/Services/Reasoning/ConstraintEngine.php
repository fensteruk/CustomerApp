<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\ConstraintFinding;
use App\Wald\Contracts\Reasoning\ReasoningConstraint;

final class ConstraintEngine
{
    private array $constraints;

    public function __construct(?array $constraints = null)
    {
        $this->constraints = $constraints ?? [new SourceContextConstraint, new ExclusiveRoleConstraint];
        if (count($this->constraints) > 64) {
            throw new ReasoningProblem('invalid_constraint_registry');
        }
        $seen = [];
        foreach ($this->constraints as $constraint) {
            if (! $constraint instanceof ReasoningConstraint) {
                throw new ReasoningProblem('invalid_constraint_registry');
            }
            $definition = $constraint->definition();
            foreach (['key', 'version', 'scope'] as $field) {
                if (! is_string($definition[$field] ?? null)) {
                    throw new ReasoningProblem('invalid_constraint_registry');
                }
            }
            if (! in_array($definition['phase'] ?? 'reduction', ['reduction', 'resolution'], true)) {
                throw new ReasoningProblem('invalid_constraint_registry');
            }
            if (isset($seen[$definition['key']]) || ! preg_match('/^wald\.[a-z0-9_.]+$/D', $definition['key']) || ! preg_match('/^[1-9][0-9]*$/D', $definition['version']) || ! in_array($definition['scope'], ['hypothesis', 'column', 'region', 'workbook'], true)) {
                throw new ReasoningProblem('invalid_constraint_registry');
            }
            $seen[$definition['key']] = true;
        }
        usort($this->constraints, fn ($a, $b) => strcmp($a->definition()['key'], $b->definition()['key']));
    }

    public function snapshot(): array
    {
        return array_map(fn ($constraint) => [...$constraint->definition(), 'phase' => $constraint->definition()['phase'] ?? 'reduction'], $this->constraints);
    }

    public function apply(array $candidates, ConfidencePolicy $policy): array
    {
        $trace = [];
        // One reduction pass, then one resolution pass over adjusted scores. No iteration.
        foreach (['reduction', 'resolution'] as $phase) {
            $findingsForPhase = $this->evaluatePhase($phase, $candidates, $policy);
            foreach ($findingsForPhase as $finding) {
                foreach ($finding['candidate_ids'] as $id) {
                    $candidates[$id]['constraints'][] = $finding;
                    if ($finding['outcome'] === 'adjustment') {
                        $candidates[$id]['scoring']['score'] = max(0, $candidates[$id]['scoring']['score'] + $finding['adjustment']);
                    } elseif ($finding['outcome'] === 'contradiction') {
                        $candidates[$id]['scoring']['veto'] = true;
                    }
                }
            }
            $trace = [...$trace, ...$findingsForPhase];
            if (count($trace) > 30000) {
                throw new ReasoningProblem('reasoning_limit_exceeded');
            }
        }

        return ['candidates' => $candidates, 'trace' => $trace];
    }

    private function evaluatePhase(string $phase, array $candidates, ConfidencePolicy $policy): array
    {
        $trace = [];
        foreach ($this->constraints as $constraint) {
            if (($constraint->definition()['phase'] ?? 'reduction') !== $phase) {
                continue;
            }
            try {
                $findings = $constraint->evaluate($candidates, $policy);
            } catch (\Throwable) {
                throw new ReasoningProblem('constraint_evaluation_failed');
            }
            foreach ($findings as $finding) {
                if (! $finding instanceof ConstraintFinding || $finding->ruleKey !== $constraint->definition()['key'] || $finding->version !== $constraint->definition()['version'] || array_diff($finding->candidateIds, array_keys($candidates)) !== [] || ($phase === 'resolution' && $finding->outcome === 'adjustment')) {
                    throw new ReasoningProblem('invalid_constraint_result');
                }
                $trace[] = $finding->toArray();
                if (count($trace) > 30000) {
                    throw new ReasoningProblem('reasoning_limit_exceeded');
                }
            }
        }

        return $trace;
    }
}
