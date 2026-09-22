<?php

namespace App\Policies;

use App\Enums\PortalRoleIdentifier;
use App\Models\PortalRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class OfficeAdministrationPolicy
{
    public const ABILITIES = [
        'view',
        'customer_create',
        'customer_update',
        'customer_deactivate',
        'customer_reactivate',
        'customer_delete',
        'site_create',
        'site_update',
        'site_deactivate',
        'site_reactivate',
        'site_delete',
        'user_create',
        'user_update',
        'user_deactivate',
        'user_reactivate',
        'user_assign_sites',
        'settings_update',
    ];

    public function authorize(User $actor, string $ability, bool $lock = false): User
    {
        if (! in_array($ability, self::ABILITIES, true)
            || ! $actor->exists
            || $actor->is_preview_user
            || $actor->isDirty(['portal_role_id', 'is_active', 'is_preview_user'])) {
            throw new AuthorizationException('Office administration access denied.');
        }

        if ($actor->relationLoaded('portalRole')) {
            $loadedRole = $actor->getRelation('portalRole');

            if (! $loadedRole || (int) $loadedRole->getKey() !== (int) $actor->portal_role_id) {
                throw new AuthorizationException('Office administration access denied.');
            }
        }

        if ($lock && DB::transactionLevel() === 0) {
            throw new \LogicException('Office administration locks require a transaction.');
        }

        $userQuery = User::query()->whereKey($actor->getKey());
        $fresh = ($lock ? $userQuery->lockForUpdate() : $userQuery)->first();

        $roleQuery = PortalRole::query()->whereKey($fresh?->portal_role_id);
        $role = ($lock ? $roleQuery->sharedLock() : $roleQuery)->first();

        if (! $fresh
            || ! $fresh->is_active
            || $fresh->is_preview_user
            || $role?->identifier !== PortalRoleIdentifier::FensterOfficeStaff->value) {
            throw new AuthorizationException('Office administration access denied.');
        }

        return $fresh->setRelation('portalRole', $role);
    }

    public function allows(User $actor, string $ability = 'view'): bool
    {
        try {
            $this->authorize($actor, $ability);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }
}
