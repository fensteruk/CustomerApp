<?php

namespace App\SourceImport\Knowledge\Models;

final class Clarification extends KnowledgeRecord
{
    protected $table = 'wald_clarifications';

    protected function casts(): array
    {
        return [];
    }
}
