<?php

namespace App\Policies;

use App\Models\CustomerOrganisation;
use App\Models\User;

class CustomerOrganisationPolicy
{
    public function viewAny(User $user): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user);
    }

    public function view(User $user, CustomerOrganisation $customerOrganisation): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user);
    }

    public function create(User $user): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'customer_create');
    }

    public function update(User $user, CustomerOrganisation $customerOrganisation): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'customer_update');
    }

    public function deactivate(User $user, CustomerOrganisation $customerOrganisation): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'customer_deactivate');
    }

    public function reactivate(User $user, CustomerOrganisation $customerOrganisation): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($user, 'customer_reactivate');
    }

    public function delete(User $user, CustomerOrganisation $customerOrganisation): bool
    {
        return false;
    }
}
