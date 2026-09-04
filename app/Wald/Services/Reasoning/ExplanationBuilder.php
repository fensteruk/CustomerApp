<?php

namespace App\Wald\Services\Reasoning;

final class ExplanationBuilder
{
    public function build(array $candidate): array
    {
        $evidence = $candidate['evidence'];
        usort($evidence, fn ($a, $b) => $b['contribution'] <=> $a['contribution'] ?: strcmp($a['evidence_id'], $b['evidence_id']));
        $support = array_values(array_filter($evidence, fn ($item) => $item['direction'] === 'supporting'));
        $counter = array_values(array_filter($evidence, fn ($item) => $item['direction'] === 'contradicting'));

        return ['why_wald_thinks_this' => array_map(fn ($item) => ['text' => $item['explanation'], 'evidence_id' => $item['evidence_id']], array_slice($support, 0, 3)),
            'what_makes_wald_uncertain' => array_map(fn ($item) => ['text' => $item['explanation'], 'evidence_id' => $item['evidence_id']], array_slice($counter, 0, 3)),
            'decision_reasons' => $candidate['confidence']['reasons'], 'competing_candidates' => $candidate['competitors']];
    }
}
