<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\CallOffBatchOperationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallOffBatchOperationItem extends Model
{
    /** @use HasFactory<CallOffBatchOperationItemFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'call_off_batch_operation_id',
        'call_off_request_id',
        'before_state',
        'after_state',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CallOffBatchOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(CallOffBatchOperation::class, 'call_off_batch_operation_id');
    }

    /**
     * @return BelongsTo<CallOffRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(CallOffRequest::class, 'call_off_request_id');
    }
}
