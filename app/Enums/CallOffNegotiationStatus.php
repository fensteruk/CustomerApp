<?php

namespace App\Enums;

enum CallOffNegotiationStatus: string
{
    case Open = 'open';
    case DateAgreed = 'date_agreed';
    case Withdrawn = 'withdrawn';
    case Completed = 'completed';
    case Superseded = 'superseded';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
