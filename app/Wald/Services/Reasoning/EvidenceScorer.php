<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\Evidence;
use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\EvidenceProvenanceVerifier;
use App\Wald\Contracts\Reasoning\EvidenceTrust;

final class EvidenceScorer
{
    public function __construct(private readonly ?EvidenceProvenanceVerifier $provenance = null) {}

    /** @param list<Evidence> $evidence */
    public function score(array $evidence, array $reliability): array
    {
        $groups = $trace = [];
        $veto = false;
        foreach ($evidence as $item) {
            if (! $item instanceof Evidence || ! array_key_exists($item->family, ConfidencePolicy::FAMILY_CAPS) || ($item->trust !== EvidenceTrust::Structural && ! ($this->provenance?->accepts($item) ?? false)) || ! is_int($reliability[$item->ruleKey] ?? null) || $reliability[$item->ruleKey] < 1 || $reliability[$item->ruleKey] > 100 || $item->correlationKey === '') {
                throw new ReasoningProblem('invalid_evidence_provenance');
            }
            $effective = intdiv($item->weight * $reliability[$item->ruleKey], 100);
            $key = $item->direction->value.':'.$item->family.':'.$item->correlationKey;
            $groups[$key][] = ['item' => $item, 'weight' => $effective];
            $veto = $veto || $item->veto;
        }
        ksort($groups);
        $positive = $negative = [];
        foreach ($groups as $group) {
            usort($group, fn ($a, $b) => $b['weight'] <=> $a['weight'] ?: strcmp($a['item']->id, $b['item']->id));
            foreach ($group as $index => $entry) {
                $item = $entry['item'];
                $counted = $index === 0 && $item->direction !== EvidenceDirection::Informational;
                $trace[] = ['evidence_id' => $item->id, 'effective_weight' => $entry['weight'], 'counted_before_family_cap' => $counted, 'suppression' => $counted ? null : 'correlated_or_informational'];
                if ($counted && $item->direction === EvidenceDirection::Supporting) {
                    $positive[$item->family] = ($positive[$item->family] ?? 0) + $entry['weight'];
                } elseif ($counted && $item->direction === EvidenceDirection::Contradicting) {
                    $negative[$item->family] = ($negative[$item->family] ?? 0) + $entry['weight'];
                }
            }
        }
        $families = [];
        foreach (ConfidencePolicy::FAMILY_CAPS as $family => $cap) {
            $families[$family] = ['raw_support' => $positive[$family] ?? 0, 'support' => min($cap, $positive[$family] ?? 0), 'cap' => $cap, 'penalty' => $negative[$family] ?? 0];
        }
        $support = array_sum(array_column($families, 'support'));
        $penalty = array_sum($negative);
        $raw = $support - $penalty;

        return ['support' => $support, 'penalty' => $penalty, 'raw_score' => $raw, 'score' => max(0, min(100, $raw)), 'veto' => $veto,
            'supporting_families' => count(array_filter($families, fn ($family) => $family['support'] > 0)), 'families' => $families, 'evidence_accounting' => $trace];
    }
}
