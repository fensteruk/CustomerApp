<?php

namespace App\Wald\Contracts\Reasoning;

enum EvidenceDirection: string
{
    case Supporting = 'supporting';
    case Contradicting = 'contradicting';
    case Informational = 'informational';
}
