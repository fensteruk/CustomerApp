<?php

namespace App\Enums;

enum AdministrativeAction: string
{
    case Created = 'created';
    case Renamed = 'renamed';
    case Updated = 'updated';
    case Deactivated = 'deactivated';
    case Reactivated = 'reactivated';
    case RoleChanged = 'role_changed';
    case CustomerChanged = 'customer_changed';
    case SiteAssigned = 'site_assigned';
    case SiteRemoved = 'site_removed';
}
