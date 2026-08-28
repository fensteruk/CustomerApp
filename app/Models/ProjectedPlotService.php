<?php

namespace App\Models;

use App\Enums\CallOffServiceType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer Portal projection for one independently customer-callable plot service.
 *
 * It is populated by a future source synchronisation adapter; Portal users cannot edit
 * source identifiers, completion facts, stages or product eligibility inputs.
 */
class ProjectedPlotService extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'projected_plot_id',
        'service_identifier',
        'source_call_number',
        'source_call_type',
        'source_job_stage',
        'source_completion_flag',
        'source_completed_at',
        'source_completion_observed_at',
        'source_updated_at',
        'last_observed_at',
        'source_present',
        'source_missing_since',
        'last_source_import_run_id',
    ];

    protected function casts(): array
    {
        return [
            'service_identifier' => CallOffServiceType::class,
            'source_completed_at' => 'date',
            'source_completion_flag' => 'boolean',
            'source_completion_observed_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'last_observed_at' => 'datetime',
            'source_present' => 'boolean',
            'source_missing_since' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProjectedPlot, $this> */
    public function projectedPlot(): BelongsTo
    {
        return $this->belongsTo(ProjectedPlot::class);
    }

    /** @return BelongsTo<SourceImportRun, $this> */
    public function lastSourceImportRun(): BelongsTo
    {
        return $this->belongsTo(SourceImportRun::class, 'last_source_import_run_id');
    }

    /** @return HasMany<CallOffRequest, $this> */
    public function callOffRequests(): HasMany
    {
        return $this->hasMany(CallOffRequest::class);
    }

    public function isSourceCompleted(): bool
    {
        return $this->source_completed_at !== null || $this->source_completion_observed_at !== null || $this->source_completion_flag === true;
    }
}
