<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ImportPolicy
{
    public const ABILITIES = ['binding_draft', 'binding_activate', 'binding_revoke', 'binding_audit', 'upload', 'analyse', 'clarify', 'review', 'commit', 'audit', 'retry'];

    public function authorize(User $actor, KnowledgeScope $scope, string $ability, bool $lock = false): User
    {
        if (! (config('wald_import.enabled', false) || (new WaldPilotAvailability)->enabled()) || ! in_array($ability, self::ABILITIES, true) || ! $actor->exists || $actor->is_preview_user) {
            throw new AuthorizationException('Import access denied.');
        }
        $query = User::query()->whereKey($actor->getKey());
        $fresh = ($lock ? $query->lockForUpdate() : $query)->first();
        $query = DB::table('portal_roles')->where('id', $fresh?->portal_role_id);
        $role = ($lock ? $query->sharedLock() : $query)->first()?->identifier;
        if (! $fresh || ! $fresh->is_active || $fresh->is_preview_user || $role !== 'fenster_office_staff') {
            throw new AuthorizationException('Import access denied.');
        }
        $owner = DB::table('customer_organisations')->where('id', $scope->organisationId);
        $site = DB::table('sites')->where('id', $scope->siteId)->where('customer_organisation_id', $scope->organisationId);
        if (! ($lock ? $owner->lockForUpdate() : $owner)->first() || ! ($lock ? $site->sharedLock() : $site)->first()) {
            throw new AuthorizationException('Import scope denied.');
        }

        return $fresh;
    }
}
