<?php

namespace App\Enums;

/** Customer-facing aggregate state for a four-service projected plot. */
enum PlotOverallStatus: string
{
    case NothingCalledOff = 'nothing_called_off';
    case CallOffsInProgress = 'call_offs_in_progress';
    case DatesAgreed = 'dates_agreed';
    case PartiallyCompleted = 'partially_completed';
    case FullyCompleted = 'fully_completed';

    public function label(): string
    {
        return match ($this) {
            self::NothingCalledOff => 'Nothing Called Off',
            self::CallOffsInProgress => 'Call-Offs In Progress',
            self::DatesAgreed => 'Dates Agreed',
            self::PartiallyCompleted => 'Partially Completed',
            self::FullyCompleted => 'Fully Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NothingCalledOff => 'slate',
            self::CallOffsInProgress => 'amber',
            self::DatesAgreed => 'emerald',
            self::PartiallyCompleted => 'sky',
            self::FullyCompleted => 'indigo',
        };
    }
}
