<?php

namespace App\Enums;

enum AdministrativeEntityType: string
{
    case CustomerOrganisation = 'customer_organisation';
    case Site = 'site';
    case User = 'user';
}
