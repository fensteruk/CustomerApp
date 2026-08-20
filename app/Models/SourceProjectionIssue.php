<?php

namespace App\Models;

use App\Enums\SourceProjectionIssueType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceProjectionIssue extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['source_import_run_id', 'projected_plot_service_id', 'issue_key', 'issue_type', 'source_call_number', 'context', 'first_detected_at', 'last_detected_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['issue_type' => SourceProjectionIssueType::class, 'context' => 'array', 'first_detected_at' => 'datetime', 'last_detected_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<SourceImportRun, $this> */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(SourceImportRun::class, 'source_import_run_id');
    }

    /** @return BelongsTo<ProjectedPlotService, $this> */
    public function projectedPlotService(): BelongsTo
    {
        return $this->belongsTo(ProjectedPlotService::class);
    }
}
