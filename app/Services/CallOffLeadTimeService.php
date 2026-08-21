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
        $plot = $service->projectedPlot;
        $products = $plot === null
            ? collect()
            : ($plot->relationLoaded('products') ? $plot->products : $plot->products()->get());
        $weeks = $products->contains(fn ($product): bool => $product->isBifold()) ? 5 : 4;

        return $this->nextWorkingDay($from->addWeeks($weeks));
    }

    public function latestNormalDate(?CarbonInterface $from = null): CarbonImmutable
    {
        return CarbonImmutable::instance($from ?? now())->startOfDay()->addMonthsNoOverflow(6);
    }

    public function isPermittedRequestedDate(CarbonInterface $requestedDate, ?CarbonInterface $from = null): bool
    {
        $requestedDate = CarbonImmutable::instance($requestedDate)->startOfDay();

        return ! $requestedDate->isWeekend()
            && ! $this->holidays->isHoliday($requestedDate)
            && $requestedDate->lessThanOrEqualTo($this->latestNormalDate($from));
    }

    public function isWithinNormalWindow(ProjectedPlotService $service, CarbonInterface $requestedDate, ?CarbonInterface $from = null): bool
    {
        $requestedDate = CarbonImmutable::instance($requestedDate)->startOfDay();

        return $this->isPermittedRequestedDate($requestedDate, $from)
            && $requestedDate->greaterThanOrEqualTo($this->earliestNormalDate($service, $from));
    }

    private function nextWorkingDay(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend() || $this->holidays->isHoliday($date)) {
            $date = $date->addDay();
        }

        return $date;
    }
}
