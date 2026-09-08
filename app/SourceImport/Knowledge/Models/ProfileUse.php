<?php

namespace App\SourceImport\Knowledge\Models;

final class ProfileUse extends KnowledgeRecord
{
    protected $table = 'wald_profile_uses';

    protected function casts(): array
    {
        return ['applied' => 'boolean', 'pins' => CanonicalJson::class, 'selection' => CanonicalJson::class];
    }
}
