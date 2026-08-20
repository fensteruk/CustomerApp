<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_organisation_id',
        'name',
        'location',
        'external_source',
        'external_identifier',
    ];

    /**
     * @return BelongsTo<CustomerOrganisation, $this>
     */
    public function customerOrganisation(): BelongsTo
    {
        return $this->belongsTo(CustomerOrganisation::class);
    }

    /**
     * @return HasMany<SiteUserAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SiteUserAssignment::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_user_assignments')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ProjectedPlot, $this>
     */
    public function projectedPlots(): HasMany
    {
        return $this->hasMany(ProjectedPlot::class);
    }

    /**
     * @return HasMany<CallOffBatch, $this>
     */
    public function callOffBatches(): HasMany
    {
        return $this->hasMany(CallOffBatch::class);
    }

    /**
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('customer_organisation_id', $user->customer_organisation_id)
            ->whereHas('assignedUsers', fn (Builder $assignedUsers): Builder => $assignedUsers->whereKey($user->getKey()));
    }
}
