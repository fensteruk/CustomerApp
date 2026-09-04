<?php

namespace App\Wald\Contracts\Reasoning;

final readonly class Hypothesis
{
    public string $id;

    public function __construct(public HypothesisDefinition $definition, public array $target)
    {
        $this->id = $target['id'].'#'.$definition->key;
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'definition' => $this->definition->toArray(), 'target' => $this->target];
    }
}
