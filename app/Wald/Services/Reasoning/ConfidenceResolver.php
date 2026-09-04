<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\RiskClass;

final class ConfidenceResolver
{
    public function resolve(array $candidate, int $margin, bool $leading, ConfidencePolicy $policy): array
    {
        $score = $candidate['scoring'];
        $risk = RiskClass::from($candidate['hypothesis']['definition']['risk']);
        $thresholds = $policy->thresholds($risk);
        $outcomes = array_column($candidate['constraints'], 'outcome');
        // WALD01: an explicit confirmed source interpretation is not a score of 100.
        // Scorer provenance validation has already checked this trusted rule's fact.
        $humanConfirmed = count(array_filter($candidate['evidence'] ?? [], fn ($e) => $e['trust'] === 'confirmed_profile' && $e['rule_key'] === 'wald.clarification.confirmed_mapping')) > 0;
        $reasons = [];
        if ($score['veto']) {
            $decision = 'contradiction';
            $reasons[] = 'decisive_contradiction';
        } elseif (in_array('rejected', $outcomes, true) || ! $leading) {
            $decision = 'rejected';
            $reasons[] = 'stronger_competing_candidate';
        } elseif ($score['score'] < ConfidencePolicy::PLAUSIBLE) {
            $decision = 'insufficient_evidence';
            $reasons[] = 'not_enough_support';
        } else {
            if ($margin < $thresholds['margin']) {
                $reasons[] = 'candidate_margin_too_small';
            }
            if ($score['supporting_families'] < $thresholds['families']) {
                $reasons[] = 'insufficient_evidence_diversity';
            }
            if ($score['penalty'] > 0) {
                $reasons[] = 'unresolved_counter_evidence';
            }
            if (in_array('clarification', $outcomes, true)) {
                $reasons = [...$reasons, ...array_column(array_filter($candidate['constraints'], fn ($finding) => $finding['outcome'] === 'clarification'), 'reason')];
            }
            if ($thresholds['full_validation'] && ! $candidate['critical_full_validation']) {
                $reasons[] = 'full_candidate_validation_required';
            }
            if ($reasons !== []) {
                $decision = 'clarification_required';
            } elseif ($score['score'] >= $thresholds['strength'] || $humanConfirmed) {
                $decision = 'accepted';
            } elseif ($risk === RiskClass::Descriptive && $score['score'] >= ConfidencePolicy::REVIEWABLE) {
                $decision = 'accepted_reviewable';
                $reasons[] = 'review_before_use';
            } else {
                $decision = 'clarification_required';
                $reasons[] = 'risk_threshold_not_met';
            }
        }
        $band = match ($decision) {
            'accepted' => 'high_confidence', 'accepted_reviewable', 'clarification_required' => 'needs_confirmation', default => 'unrecognised',
        };

        $confidence = ['band' => $band, 'strength' => $score['score'], 'is_probability' => false, 'risk_class' => $risk->value, 'margin' => $margin, 'requirements' => $thresholds, 'supporting_families' => $score['supporting_families'], 'reasons' => array_values(array_unique($reasons))];
        if ($humanConfirmed) {
            $confidence['human_confirmed'] = true;
        }

        return ['decision' => $decision, 'confidence' => $confidence];
    }
}
