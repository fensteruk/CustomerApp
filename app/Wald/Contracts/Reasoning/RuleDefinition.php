<?php

namespace App\Wald\Contracts\Reasoning;

final readonly class RuleDefinition
{
    public function __construct(
        public string $key,
        public string $version,
        public RulePhase $phase,
        public string $description,
        public array $requires = [],
        public array $dependsOn = [],
        public bool $enabled = true,
        public int $priority = 100,
        public bool $critical = true,
        public int $reliability = 100,
        public int $maxEvidence = 4,
    ) {}

    public function toArray(): array
    {
        return ['key' => $this->key, 'version' => $this->version, 'phase' => $this->phase->name, 'description' => $this->description, 'requires' => $this->requires, 'depends_on' => $this->dependsOn, 'enabled' => $this->enabled, 'priority' => $this->priority, 'critical' => $this->critical, 'reliability' => $this->reliability, 'max_evidence' => $this->maxEvidence];
    }
}
