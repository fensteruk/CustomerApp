<?php

namespace App\Wald\Contracts\Reasoning;

use App\Wald\Services\Reasoning\ReasoningProblem;

final readonly class Evidence
{
    public string $id;

    public function __construct(
        public string $ruleKey,
        public string $ruleVersion,
        public string $hypothesisId,
        public EvidenceDirection $direction,
        public int $weight,
        public string $family,
        public string $correlationKey,
        public string $explanationCode,
        public string $explanation,
        public array $observation,
        public array $sourceRefs,
        public bool $veto = false,
        public array $limitations = [],
        public EvidenceTrust $trust = EvidenceTrust::Structural,
    ) {
        if ($weight < 0 || $weight > 100 || ($direction === EvidenceDirection::Informational && $weight !== 0) || ($veto && $direction !== EvidenceDirection::Contradicting)) {
            throw new ReasoningProblem('invalid_evidence_weight');
        }
        $this->id = hash('sha256', json_encode([$ruleKey, $ruleVersion, $hypothesisId, $direction->value, $family, $correlationKey, $explanationCode, $observation, $sourceRefs], JSON_THROW_ON_ERROR));
    }

    public function toArray(): array
    {
        return ['evidence_id' => $this->id, 'rule_key' => $this->ruleKey, 'rule_version' => $this->ruleVersion, 'candidate_id' => $this->hypothesisId,
            'direction' => $this->direction->value, 'contribution' => $this->weight, 'evidence_family' => $this->family, 'correlation_key' => $this->correlationKey,
            'explanation_code' => $this->explanationCode, 'explanation' => $this->explanation, 'explanation_parameters' => $this->observation, 'observation' => $this->observation,
            'source_refs' => $this->sourceRefs, 'veto' => $this->veto, 'coverage' => 'wald02_bounded_profile', 'limitations' => $this->limitations,
            'trust' => $this->trust->value, 'profile_ref' => null, 'dictionary_ref' => null, 'clarification_ref' => null];
    }
}
