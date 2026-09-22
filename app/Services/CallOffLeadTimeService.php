<?php

namespace App\Services;

use App\Contracts\HolidayProvider;
use App\Enums\CallOffServiceType;
use App\Models\ProjectedPlotService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CallOffLeadTimeService
{
    public const CAVITY_CLOSER_WORKING_DAYS = 15;

    public function __construct(private readonly HolidayProvider $holidays) {}

    public function earliestNormalDate(ProjectedPlotService $service, ?CarbonInterface $from = null): CarbonImmutable
    {
        if ($service->service_identifier === CallOffServiceType::CavityClosers) {
            return $this->earliestCavityCloserDate($from);
        }

        return $this->earliestAmendmentDate($service, $from);
    }

    public function earliestAmendmentDate(ProjectedPlotService $service, ?CarbonInterface $from = null): CarbonImmutable
    {

        $from = CarbonImmutable::instance($from ?? now())->startOfDay();
        $plot = $service->projectedPlot;
        $products = $plot === null
            ? collect()
            : ($plot->relationLoaded('products') ? $plot->products : $plot->products()->get());
        $weeks = $products->contains(fn ($product): bool => $product->isBifold()) ? 5 : 4;

        return $this->nextWorkingDay($from->addWeeks($weeks));
    }

    public function earliestCavityCloserDate(?CarbonInterface $from = null): CarbonImmutable
    {
        $date = CarbonImmutable::instance($from ?? now())->startOfDay();
        for ($days = 0; $days < self::CAVITY_CLOSER_WORKING_DAYS;) {
            $date = $date->addDay();
            if ($this->isWorkingDay($date)) {
                $days++;
            }
        }

        return $date;
    }

    public function workingDaysEarly(CarbonInterface $selected, CarbonInterface $earliest): int
    {
        $date = CarbonImmutable::instance($selected)->startOfDay();
        $earliest = CarbonImmutable::instance($earliest)->startOfDay();
        $days = 0;
        while ($date->lt($earliest)) {
            $date = $date->addDay();
            if ($this->isWorkingDay($date)) {
                $days++;
            }
        }

        return $days;
    }

    public function latestNormalDate(?CarbonInterface $from = null): CarbonImmutable
    {
        return CarbonImmutable::instance($from ?? now())->startOfDay()->addMonthsNoOverflow(6);
    }

    public function isPermittedRequestedDate(CarbonInterface $requestedDate, ?CarbonInterface $from = null): bool
    {
        $requestedDate = CarbonImmutable::instance($requestedDate)->startOfDay();

        return $this->isWorkingDay($requestedDate)
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
        while (! $this->isWorkingDay($date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function isWorkingDay(CarbonImmutable $date): bool
    {
        return ! $date->isWeekend() && ! $this->holidays->isHoliday($date);
    }
}
