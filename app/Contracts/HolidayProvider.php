<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface HolidayProvider
{
    public function isHoliday(CarbonInterface $date): bool;
}
