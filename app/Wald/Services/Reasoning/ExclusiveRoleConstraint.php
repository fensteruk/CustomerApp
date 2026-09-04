<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\ConstraintFinding;
use App\Wald\Contracts\Reasoning\ReasoningConstraint;
use App\Wald\Contracts\Reasoning\RiskClass;

final class ExclusiveRoleConstraint implements ReasoningConstraint
{
    public function definition(): array
    {
        return ['key' => 'wald.constraint.exclusive_region_role', 'version' => '1', 'scope' => 'region', 'phase' => 'resolution'];
    }

    public function evaluate(array $candidates, ConfidencePolicy $policy): array
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $definition = $candidate['hypothesis']['definition'];
            if ($definition['exclusive_within_region'] === null || $candidate['scoring']['veto'] || $candidate['scoring']['score'] < ConfidencePolicy::PLAUSIBLE || in_array('rejected', array_column($candidate['constraints'], 'outcome'), true)) {
                continue;
            }
            $key = $candidate['hypothesis']['target']['region_id'].'#'.$definition['exclusive_within_region'];
            $groups[$key][] = $candidate;
        }
        $findings = [];
        foreach ($groups as $group) {
            usort($group, fn ($a, $b) => $b['scoring']['score'] <=> $a['scoring']['score'] ?: strcmp($a['hypothesis']['id'], $b['hypothesis']['id']));
            $required = $policy->thresholds(RiskClass::from($group[0]['hypothesis']['definition']['risk']))['margin'];
            $near = array_filter($group, fn ($candidate) => $group[0]['scoring']['score'] - $candidate['scoring']['score'] < $required);
            if (count($near) > 1) {
                $findings[] = new ConstraintFinding($this->definition()['key'], '1', 'clarification', array_column(array_column($near, 'hypothesis'), 'id'), 'exclusive_role_near_tie');
            } else {
                $findings[] = new ConstraintFinding($this->definition()['key'], '1', 'pass', [$group[0]['hypothesis']['id']], 'exclusive_role_has_clear_leader');
            }
            foreach ($group as $candidate) {
                if (! in_array($candidate, $near, true)) {
                    $findings[] = new ConstraintFinding($this->definition()['key'], '1', 'rejected', [$candidate['hypothesis']['id']], 'stronger_exclusive_role_candidate');
                }
            }
        }

        return $findings;
    }
}
