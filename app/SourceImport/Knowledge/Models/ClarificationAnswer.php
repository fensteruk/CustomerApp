<?php

namespace App\SourceImport\Knowledge\Models;

final class ClarificationAnswer extends KnowledgeRecord
{
    protected $table = 'wald_clarification_answers';

    protected function casts(): array
    {
        return ['reusable_intent' => 'boolean'];
    }
}
