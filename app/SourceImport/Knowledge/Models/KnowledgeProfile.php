<?php

namespace App\SourceImport\Knowledge\Models;

use App\SourceImport\Knowledge\ProfileState;

final class KnowledgeProfile extends KnowledgeRecord
{
    protected $table = 'wald_profiles';

    protected function casts(): array
    {
        return ['state' => ProfileState::class, 'review_due_at' => 'immutable_datetime', 'retired_at' => 'immutable_datetime', 'retain_until' => 'immutable_datetime'];
    }
}
