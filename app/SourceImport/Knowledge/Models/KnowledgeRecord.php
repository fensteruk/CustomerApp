<?php

namespace App\SourceImport\Knowledge\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** Private records: no mass-assigned authority or default payload serialization. */
abstract class KnowledgeRecord extends Model
{
    use HasUuid;

    protected $guarded = ['*'];

    protected $visible = ['uuid'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
