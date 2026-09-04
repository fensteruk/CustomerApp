<?php

namespace App\Wald\Contracts\Reasoning;

enum RiskClass: string
{
    case Critical = 'critical';
    case Material = 'material';
    case Descriptive = 'descriptive';
}
