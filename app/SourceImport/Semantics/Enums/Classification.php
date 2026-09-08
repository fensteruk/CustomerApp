<?php

namespace App\SourceImport\Semantics\Enums;

enum Classification: string
{
    case Confirmed = 'CONFIRMED';
    case Unknown = 'UNKNOWN';
    case Ambiguous = 'AMBIGUOUS';
    case Invalid = 'INVALID';
    case Ignored = 'IGNORED';
}
