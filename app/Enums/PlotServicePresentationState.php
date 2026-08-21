<?php

namespace App\Enums;

/**
 * Customer-facing service state for the plot overview.
 *
 * This is deliberately a presentation projection, not a persisted call-off lifecycle.
 */
enum PlotServicePresentationState: string
{
    case NotCalledOff = 'not_called_off';
    case AwaitingDate = 'awaiting_date';
    case DateAgreed = 'date_agreed';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotCalledOff => 'Not Called Off',
            self::AwaitingDate => 'Called Off — Awaiting Date',
            self::DateAgreed => 'Date Agreed',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NotCalledOff => 'slate',
            self::AwaitingDate => 'amber',
            self::DateAgreed => 'emerald',
            self::Completed => 'sky',
        };
    }
}
