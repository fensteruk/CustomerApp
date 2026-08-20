<?php

namespace App\Services;

use App\Contracts\HolidayProvider;
use Carbon\CarbonInterface;

/**
 * Temporary calendar contract implementation.
 *
 * It intentionally knows no UK bank holidays. Sprint 3C must bind an approved data
 * provider before normal-date validation is enabled for customer submissions.
 */
class WeekdayHolidayProvider implements HolidayProvider
{
    public function isHoliday(CarbonInterface $date): bool
    {
        return false;
    }
}
