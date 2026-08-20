<?php

namespace App\Services;

use App\Enums\SourceProjectionIssueType;
use App\Models\ProjectedPlotService;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionIssue;

class SourceProjectionIssueService
{
    /** @param array<string, mixed> $context */
    public function record(SourceImportRun $run, SourceProjectionIssueType $type, string $key, ?string $callNumber = null, ?ProjectedPlotService $service = null, array $context = []): void
    {
        $issue = SourceProjectionIssue::query()->firstOrNew(['issue_key' => $key]);
        $issue->fill([
            'source_import_run_id' => $run->id,
            'projected_plot_service_id' => $service?->id,
            'issue_type' => $type,
            'source_call_number' => $callNumber,
            'context' => $context,
            'last_detected_at' => now(),
            'resolved_at' => null,
        ]);
        $issue->first_detected_at ??= now();
        $issue->save();
    }
}
