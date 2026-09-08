<?php

namespace App\SourceImport\Knowledge;

/** Separate human provenance; never a forged accepted Wald result or commit permission. */
final readonly class ReviewedInterpretation implements \JsonSerializable
{
    public function __construct(public array $original, public ?array $selection, public string $answerUuid) {}

    public function jsonSerialize(): array
    {
        return ['original' => $this->original, 'human_selection' => $this->selection,
            'answer_uuid' => $this->answerUuid, 'ready_for_staging' => false];
    }
}
