<?php

namespace App\SourceImport\Knowledge\Models;

final class KnowledgeContext extends KnowledgeRecord
{
    protected $table = 'wald_knowledge_contexts';

    protected function casts(): array
    {
        return ['pins' => CanonicalJson::class, 'expires_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }
}
