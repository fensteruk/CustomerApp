<?php

namespace App\SourceImport\Integration;

use App\Wald\Services\AnalysisBudget;
use App\Wald\Services\AnalysisProblem;

/** CustomerApp analysis envelope, not permission to enlarge a selected-site Apply. */
final class WorkbookAnalysisCapacity
{
    public const VERSION = 'customerapp.workbook-analysis-capacity.v1';

    public const DATA_ROWS = 20_000;

    public const CELLS = 2_000_000;

    public const SECONDS = 40;

    public static function budget(): AnalysisBudget
    {
        // Include one physical header row. All archive, cell, string and memory guards remain.
        // Reserve five seconds of the historical 45-second HTTP envelope for reporting/cleanup.
        return new AnalysisBudget(['rows' => self::DATA_ROWS + 1, 'cells' => self::CELLS, 'seconds' => self::SECONDS]);
    }

    public static function guardDuration(int $started): void
    {
        if ((hrtime(true) - $started) / 1_000_000_000 > self::SECONDS) {
            throw new AnalysisProblem('resource_limit_exceeded', ['limit' => 'analysis_seconds', 'maximum' => self::SECONDS]);
        }
    }
}
