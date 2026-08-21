<?php

namespace App\Data;

use App\Enums\CallOffServiceType;
use App\Enums\PlotServicePresentationState;
use Carbon\CarbonInterface;

readonly class PlotServiceOverview
{
    public function __construct(
        public CallOffServiceType $service,
        public PlotServicePresentationState $state,
        public ?CarbonInterface $date = null,
    ) {}
}
