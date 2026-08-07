<?php

namespace App\Models;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\CallOffStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallOffStatusHistory extends Model
{
    /** @use HasFactory<CallOffStatusHistoryFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'call_off_request_id',
        'call_off_batch_id',
        'call_off_batch_operation_id',
        'performed_by_user_id',
        'sequence',
        'event_type',
        'previous_status',
        'new_status',
        'before_state',
        'after_state',
        'customer_response',
        'internal_reason',
        'performed_at',
    ];

    protected $hidden = [
        'internal_reason',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CallOffHistoryEventType::class,
            'previous_status' => CallOffRequestStatus::class,
            'new_status' => CallOffRequestStatus::class,
            'before_state' => 'array',
            'after_state' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Call-off history is append-only.');
        });

        static::deleting(static function (): void {
            throw new \LogicException('Call-off history cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<CallOffRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(CallOffRequest::class, 'call_off_request_id');
    }

    /**
     * @return BelongsTo<CallOffBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CallOffBatch::class, 'call_off_batch_id');
    }

    /**
     * @return BelongsTo<CallOffBatchOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(CallOffBatchOperation::class, 'call_off_batch_operation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
