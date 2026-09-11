<?php

namespace App\Domain\Administration;

use App\Models\CustomerOrganisation;
use App\Models\Site;

final class AdministrativeSnapshots
{
    public function customer(CustomerOrganisation $customer): array
    {
        return [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'is_active' => (bool) $customer->is_active,
            'lock_version' => (int) $customer->lock_version,
        ];
    }

    public function site(Site $site, CustomerOrganisation $customer): array
    {
        return [
            'uuid' => $site->uuid,
            'customer_uuid' => $customer->uuid,
            'name' => $site->name,
            'location' => $site->location,
            'is_active' => (bool) $site->is_active,
            'effective_is_active' => (bool) $site->is_active && (bool) $customer->is_active,
            'lock_version' => (int) $site->lock_version,
        ];
    }
}
