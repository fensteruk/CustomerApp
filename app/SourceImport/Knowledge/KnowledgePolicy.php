<?php

namespace App\SourceImport\Knowledge;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class KnowledgePolicy
{
    public const ABILITIES = ['register', 'answer', 'draft', 'activate', 'reuse', 'revoke', 'audit', 'evidence', 'retention'];

    public function authorize(User $actor, KnowledgeScope $scope, string $ability, bool $lock = false): User
    {
        if (! in_array($ability, self::ABILITIES, true) || ! $actor->exists || $actor->is_preview_user) {
            throw new AuthorizationException('Knowledge access denied.');
        }
        $query = User::query()->whereKey($actor->getKey());
        $fresh = ($lock ? $query->lockForUpdate() : $query)->first();
        // Query actual role, not a caller-mutated or cached relationship.
        $roleQuery = DB::table('portal_roles')->where('id', $fresh?->portal_role_id);
        $role = ($lock ? $roleQuery->sharedLock() : $roleQuery)->first()?->identifier;
        if (! $fresh || ! $fresh->is_active || $fresh->is_preview_user || $role !== 'fenster_office_staff') {
            throw new AuthorizationException('Knowledge access denied.');
        }
        $owner = DB::table('customer_organisations')->where('id', $scope->organisationId);
        $site = DB::table('sites')->where('id', $scope->siteId)->where('customer_organisation_id', $scope->organisationId);
        if (! ($lock ? $owner->lockForUpdate() : $owner)->first()
            || ! ($lock ? $site->sharedLock() : $site)->first()) {
            throw new AuthorizationException('Knowledge scope denied.');
        }

        return $fresh;
    }
}
