<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceSiteBinding extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'source_namespace',
        'source_site_key',
        'source_site_key_hash',
        'original_name',
        'display_name',
        'site_id',
        'created_by_user_id',
    ];

    public static function hashFor(string $sourceSiteKey): string
    {
        return hash('sha256', trim($sourceSiteKey));
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ProjectedPlot, $this> */
    public function projectedPlots(): HasMany
    {
        return $this->hasMany(ProjectedPlot::class);
    }
}
