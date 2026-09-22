<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user);
    }

    public function view(User $user, Site $site): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user);
    }

    public function create(User $user): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'site_create');
    }

    public function update(User $user, Site $site): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'site_update');
    }

    public function deactivate(User $user, Site $site): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'site_deactivate');
    }

    public function reactivate(User $user, Site $site): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'site_reactivate');
    }

    public function delete(User $user, Site $site): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'site_delete');
    }
}
