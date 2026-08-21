<?php

namespace App\Data;

use App\Enums\PlotOverallStatus;
use App\Models\ProjectedPlot;

readonly class PlotOverview
{
    /** @param array<string, PlotServiceOverview> $services */
    public function __construct(
        public ProjectedPlot $plot,
        public array $services,
        public PlotOverallStatus $overallStatus,
    ) {}
}
