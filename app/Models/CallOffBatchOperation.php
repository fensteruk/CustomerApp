<?php

namespace App\Models;

use App\Enums\CallOffOperationType;
use App\Models\Concerns\HasUuid;
use Database\Factories\CallOffBatchOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallOffBatchOperation extends Model
{
    /** @use HasFactory<CallOffBatchOperationFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'call_off_batch_id',
        'performed_by_user_id',
        'operation_type',
        'performed_at',
        'undo_expires_at',
        'reversed_by_operation_id',
    ];

    protected function casts(): array
    {
        return [
            'operation_type' => CallOffOperationType::class,
            'performed_at' => 'datetime',
            'undo_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CallOffBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CallOffBatch::class, 'call_off_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    /**
     * @return BelongsTo<CallOffBatchOperation, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(CallOffBatchOperation::class, 'reversed_by_operation_id');
    }

    /**
     * @return HasMany<CallOffBatchOperationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CallOffBatchOperationItem::class);
    }
}
