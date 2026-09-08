<?php

namespace App\SourceImport\Knowledge;

use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\ProfileVersion;

final class ProfileProvenance
{
    public function current(ProfileVersion $version): bool
    {
        $answer = ClarificationAnswer::query()->findOrFail($version->answer_id);
        $question = Clarification::query()->findOrFail($answer->clarification_id);
        $context = KnowledgeContext::query()->findOrFail($question->context_id);

        return $question->sequence === $answer->sequence && $context->state !== 'SUPERSEDED';
    }
}
