<?php

namespace App\Enums;

enum AdministrativeAction: string
{
    case Created = 'created';
    case Renamed = 'renamed';
    case Updated = 'updated';
    case Deactivated = 'deactivated';
    case Reactivated = 'reactivated';
}
