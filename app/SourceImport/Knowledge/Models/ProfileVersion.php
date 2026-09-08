<?php

namespace App\SourceImport\Knowledge\Models;

final class ProfileVersion extends KnowledgeRecord
{
    protected $table = 'wald_profile_versions';

    protected function casts(): array
    {
        return ['definition' => CanonicalJson::class];
    }
}
