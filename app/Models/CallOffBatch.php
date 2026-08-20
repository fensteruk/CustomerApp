<?php

namespace App\Models;

use App\Enums\CallOffServiceType;
use App\Models\Concerns\HasUuid;
use Database\Factories\CallOffBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallOffBatch extends Model
{
    /** @use HasFactory<CallOffBatchFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'site_id',
        'submitted_by_user_id',
        'service_identifier',
        'requested_date',
        'customer_response',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'service_identifier' => CallOffServiceType::class,
            'requested_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<CallOffRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(CallOffRequest::class);
    }

    /**
     * Legacy batch-level service/date/message fields are retained for audit and dual-read
     * compatibility. New multi-service submissions must store those facts on requests.
     */
    public function isLegacySingleServiceBatch(): bool
    {
        return $this->service_identifier !== null;
    }

    /**
     * @return HasMany<CallOffBatchOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(CallOffBatchOperation::class);
    }
}
