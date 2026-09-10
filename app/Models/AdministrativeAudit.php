<?php

namespace App\Models;

use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrativeAudit extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_name',
        'actor_role',
        'entity_type',
        'entity_uuid',
        'action',
        'before_state',
        'after_state',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => AdministrativeEntityType::class,
            'action' => AdministrativeAction::class,
            'before_state' => 'array',
            'after_state' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Administrative audit is append-only.');
        });

        static::deleting(static function (): void {
            throw new \LogicException('Administrative audit cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
