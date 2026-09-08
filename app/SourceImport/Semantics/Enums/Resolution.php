<?php

namespace App\SourceImport\Semantics\Enums;

enum Resolution: string
{
    case Resolved = 'RESOLVED';
    case RequiresConfirmation = 'REQUIRES_CONFIRMATION';
    case Blocked = 'BLOCKED';
}
