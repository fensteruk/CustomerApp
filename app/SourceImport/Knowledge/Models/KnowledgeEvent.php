<?php

namespace App\SourceImport\Knowledge\Models;

final class KnowledgeEvent extends KnowledgeRecord
{
    protected $table = 'wald_knowledge_events';

    protected function casts(): array
    {
        return ['before_state' => CanonicalJson::class, 'after_state' => CanonicalJson::class];
    }
}
