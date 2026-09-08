<?php

namespace App\SourceImport\Knowledge\Models;

final class KnowledgeEvidence extends KnowledgeRecord
{
    protected $table = 'wald_knowledge_evidence';

    protected function casts(): array
    {
        return ['payload' => CanonicalJson::class, 'on_hold' => 'boolean', 'terminal_at' => 'immutable_datetime', 'retain_until' => 'immutable_datetime'];
    }
}
