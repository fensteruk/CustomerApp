<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkbookInterpretationProfile extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'source_namespace',
        'sheet_identifier',
        'structural_fingerprint',
        'normalised_headers',
        'type_profile',
        'confirmed_mappings',
        'snapshot_scope',
        'version',
        'confirmed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'normalised_headers' => 'array',
            'type_profile' => 'array',
            'confirmed_mappings' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
