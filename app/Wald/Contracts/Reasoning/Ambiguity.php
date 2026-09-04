<?php

namespace App\Wald\Contracts\Reasoning;

final readonly class Ambiguity
{
    public function __construct(public string $target, public array $candidates, public int $margin, public string $reason, public string $risk, public string $questionType = 'choose_candidate') {}

    public function toArray(): array
    {
        return ['target' => $this->target, 'competing_candidates' => $this->candidates, 'leading_candidate' => $this->margin === 0 ? null : ($this->candidates[0]['id'] ?? null), 'margin' => $this->margin,
            'reason' => $this->reason, 'risk_class' => $this->risk, 'required_resolution' => true, 'question_type_hint' => $this->questionType];
    }
}
