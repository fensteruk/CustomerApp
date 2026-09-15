<?php

namespace App\SourceImport\Integration;

use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class PilotImportPolicy
{
    public function authorize(User $actor, bool $lock = false): User
    {
        if (! (new WaldPilotAvailability)->enabled() || ! $actor->exists || $actor->is_preview_user) {
            throw new AuthorizationException('Pilot import access denied.');
        }
        $query = User::query()->whereKey($actor->getKey());
        $fresh = ($lock ? $query->lockForUpdate() : $query)->first();
        $role = $fresh ? DB::table('portal_roles')->where('id', $fresh->portal_role_id)->value('identifier') : null;
        if (! $fresh || ! $fresh->is_active || $fresh->is_preview_user || $role !== 'fenster_office_staff') {
            throw new AuthorizationException('Pilot import access denied.');
        }

        return $fresh;
    }

    public function activeSite(User $actor, string $uuid): Site
    {
        $this->authorize($actor);

        return Site::query()->where('uuid', $uuid)->where('is_active', true)
            ->whereHas('customerOrganisation', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();
    }
}
