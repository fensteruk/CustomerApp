<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('uuid') === null) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
