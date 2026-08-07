<?php

namespace App\Models;

use App\Enums\PortalRoleIdentifier;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortalRole extends Model
{
    protected $fillable = [
        'identifier',
        'name',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return Attribute<PortalRoleIdentifier|null, never>
     */
    protected function roleIdentifier(): Attribute
    {
        return Attribute::get(
            fn (): ?PortalRoleIdentifier => PortalRoleIdentifier::tryFrom($this->identifier),
        );
    }
}
