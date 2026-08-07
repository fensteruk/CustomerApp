<?php

namespace App\Models;

use App\Enums\PortalNotificationType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PortalNotification extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'notifiable_user_id',
        'type',
        'event_key',
        'request_uuid',
        'batch_uuid',
        'site_uuid',
        'site_name',
        'plot_reference',
        'service_identifier',
        'requested_date',
        'current_status',
        'customer_response',
        'route_name',
        'route_parameters',
        'read_at',
        'dismissed_at',
    ];

    protected $hidden = [
        'id',
        'notifiable_user_id',
        'event_key',
        'route_parameters',
    ];

    protected function casts(): array
    {
        return [
            'type' => PortalNotificationType::class,
            'requested_date' => 'date',
            'route_parameters' => 'array',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notifiable_user_id');
    }

    /** @return HasOne<CallOffRequest, $this> */
    public function request(): HasOne
    {
        return $this->hasOne(CallOffRequest::class, 'uuid', 'request_uuid');
    }

    /** @param Builder<PortalNotification> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    /** @param Builder<PortalNotification> $query */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function dismiss(): void
    {
        $this->forceFill([
            'read_at' => $this->read_at ?? now(),
            'dismissed_at' => $this->dismissed_at ?? now(),
        ])->save();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'request_uuid' => $this->request_uuid,
            'batch_uuid' => $this->batch_uuid,
            'site_uuid' => $this->site_uuid,
            'site_name' => $this->site_name,
            'plot_reference' => $this->plot_reference,
            'service_identifier' => $this->service_identifier,
            'requested_date' => $this->requested_date?->toDateString(),
            'current_status' => $this->current_status,
            'customer_response' => $this->customer_response,
            'read_at' => $this->read_at?->toIso8601String(),
            'dismissed_at' => $this->dismissed_at?->toIso8601String(),
        ];
    }
}
