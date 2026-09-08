<?php

namespace App\SourceImport\Knowledge\Models;

use App\SourceImport\Knowledge\Canonical;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** JSON text preserves numeric type for evidence hashes on both supported database engines. */
final class CanonicalJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : Canonical::json($value);
    }
}
