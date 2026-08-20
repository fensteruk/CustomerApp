<?php

namespace App\Services;

use App\Contracts\HolidayProvider;
use App\Models\ProjectedPlotService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CallOffLeadTimeService
{
    public function __construct(private readonly HolidayProvider $holidays) {}

    public function earliestNormalDate(ProjectedPlotService $service, ?CarbonInterface $from = null): CarbonImmutable
    {
        $from = CarbonImmutable::instance($from ?? now())->startOfDay();
        $weeks = $service->projectedPlot?->products()->get()->contains(fn ($product): bool => $product->isBifold()) ? 5 : 4;

        return $this->nextWorkingDay($from->addWeeks($weeks));
    }

    public function latestNormalDate(?CarbonInterface $from = null): CarbonImmutable
    {
        return CarbonImmutable::instance($from ?? now())->startOfDay()->addMonthsNoOverflow(6);
    }

    public function isWithinNormalWindow(ProjectedPlotService $service, CarbonInterface $requestedDate, ?CarbonInterface $from = null): bool
    {
        $requestedDate = CarbonImmutable::instance($requestedDate)->startOfDay();

        return ! $requestedDate->isWeekend()
            && ! $this->holidays->isHoliday($requestedDate)
            && $requestedDate->betweenIncluded($this->earliestNormalDate($service, $from), $this->latestNormalDate($from));
    }

    private function nextWorkingDay(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend() || $this->holidays->isHoliday($date)) {
            $date = $date->addDay();
        }

        return $date;
    }
}
