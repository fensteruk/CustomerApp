<?php

namespace App\SourceImport\Knowledge;

enum ProfileState: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Stale = 'STALE';
    case Revoked = 'REVOKED';
}
