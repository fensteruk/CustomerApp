<?php

namespace App\Wald\Contracts\Reasoning;

final readonly class HypothesisDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public array $headerTokens,
        public RiskClass $risk = RiskClass::Descriptive,
        public string $competitionGroup = 'column_shape',
        public ?string $exclusiveWithinRegion = null,
        public string $version = '1',
        public array $eligibleTraits = [],
    ) {}

    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'header_tokens' => $this->headerTokens, 'risk' => $this->risk->value, 'competition_group' => $this->competitionGroup, 'exclusive_within_region' => $this->exclusiveWithinRegion, 'version' => $this->version, 'kind' => 'shape_role', 'eligible_traits' => $this->eligibleTraits];
    }
}
