<?php

namespace App\SourceImport\Knowledge;

enum Compatibility: string
{
    case Exact = 'EXACT_MATCH';
    case Review = 'COMPATIBLE_WITH_REVIEW';
    case Incompatible = 'INCOMPATIBLE';
    case Stale = 'STALE_VERSION';
}
