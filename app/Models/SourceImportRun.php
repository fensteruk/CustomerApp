<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Audit contract for a future read-only SiteApp/Excel projection import.
 */
class SourceImportRun extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'source_name',
        'source_version',
        'status',
        'started_at',
        'finished_at',
        'records_seen',
        'records_applied',
        'records_created',
        'records_updated',
        'records_unchanged',
        'records_missing',
        'records_rejected',
        'reconciliation_issue_count',
        'safe_error_summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<ProjectedPlotService, $this> */
    public function projectedPlotServices(): HasMany
    {
        return $this->hasMany(ProjectedPlotService::class, 'last_source_import_run_id');
    }
}
