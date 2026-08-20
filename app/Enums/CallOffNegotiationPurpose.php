<?php

namespace App\Enums;

enum CallOffNegotiationPurpose: string
{
    case Initial = 'initial';
    case Amendment = 'amendment';
}
