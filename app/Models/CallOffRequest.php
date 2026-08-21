<?php

namespace App\Models;

use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Models\Concerns\HasUuid;
use Database\Factories\CallOffRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallOffRequest extends Model
{
    /** @use HasFactory<CallOffRequestFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'call_off_batch_id',
        'projected_plot_id',
        'projected_plot_service_id',
        'service_identifier',
        'requested_date',
        'normal_earliest_date',
        'is_early_date_exception',
        'early_date_reason',
        'agreed_date',
        'customer_response',
        'status',
        'trashed_at',
        'trash_expires_at',
        'resubmitted_from_call_off_request_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallOffRequestStatus::class,
            'service_identifier' => CallOffServiceType::class,
            'requested_date' => 'date',
            'normal_earliest_date' => 'date',
            'is_early_date_exception' => 'boolean',
            'agreed_date' => 'date',
            'trashed_at' => 'datetime',
            'trash_expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<CallOffBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CallOffBatch::class, 'call_off_batch_id');
    }

    /**
     * @return BelongsTo<ProjectedPlot, $this>
     */
    public function projectedPlot(): BelongsTo
    {
        return $this->belongsTo(ProjectedPlot::class);
    }

    /** @return BelongsTo<ProjectedPlotService, $this> */
    public function projectedPlotService(): BelongsTo
    {
        return $this->belongsTo(ProjectedPlotService::class);
    }

    /**
     * @return BelongsTo<CallOffRequest, $this>
     */
    public function resubmittedFrom(): BelongsTo
    {
        return $this->belongsTo(CallOffRequest::class, 'resubmitted_from_call_off_request_id');
    }

    /**
     * @return HasMany<CallOffRequest, $this>
     */
    public function resubmissions(): HasMany
    {
        return $this->hasMany(CallOffRequest::class, 'resubmitted_from_call_off_request_id');
    }

    /**
     * @return HasMany<CallOffStatusHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(CallOffStatusHistory::class);
    }

    /** @return HasMany<CallOffDateNegotiation, $this> */
    public function dateNegotiations(): HasMany
    {
        return $this->hasMany(CallOffDateNegotiation::class);
    }

    public function effectiveServiceIdentifier(): ?CallOffServiceType
    {
        return $this->service_identifier ?? $this->batch?->service_identifier;
    }

    public function isLegacyDateAgreed(): bool
    {
        return $this->status === CallOffRequestStatus::Approved;
    }

    /**
     * @return HasMany<CallOffBatchOperationItem, $this>
     */
    public function operationItems(): HasMany
    {
        return $this->hasMany(CallOffBatchOperationItem::class);
    }

    /**
     * @param  Builder<CallOffRequest>  $query
     * @return Builder<CallOffRequest>
     */
    public function scopeCustomerTrash(Builder $query): Builder
    {
        return $query->whereNotNull('trashed_at')
            ->where('trash_expires_at', '>', now());
    }

    /**
     * @return array<string, mixed>
     */
    public function stateSnapshot(): array
    {
        return [
            'status' => $this->status?->value,
            'requested_date' => $this->requested_date?->toDateString(),
            'agreed_date' => $this->agreed_date?->toDateString(),
            'active_conflict_key' => $this->active_conflict_key,
            'trashed_at' => $this->trashed_at?->toISOString(),
            'trash_expires_at' => $this->trash_expires_at?->toISOString(),
        ];
    }
}
