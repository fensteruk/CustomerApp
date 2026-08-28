<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\ProjectedPlotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer Portal projection.
 *
 * This is NOT the operational SiteApp Plot model.
 */
class ProjectedPlot extends Model
{
    /** @use HasFactory<ProjectedPlotFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'site_id',
        'source_site_binding_id',
        'external_source',
        'external_identifier',
        'plot_reference',
        'is_completed',
        'source_updated_at',
        'synchronised_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'source_updated_at' => 'datetime',
            'synchronised_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<SourceSiteBinding, $this> */
    public function sourceSiteBinding(): BelongsTo
    {
        return $this->belongsTo(SourceSiteBinding::class);
    }

    /**
     * @return HasMany<CallOffRequest, $this>
     */
    public function callOffRequests(): HasMany
    {
        return $this->hasMany(CallOffRequest::class);
    }

    /** @return HasMany<ProjectedPlotService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(ProjectedPlotService::class);
    }

    /** @return HasMany<ProjectedPlotProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(ProjectedPlotProduct::class);
    }

    /**
     * @param  Builder<ProjectedPlot>  $query
     * @return Builder<ProjectedPlot>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }
}
