<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecordCallOffStatusHistoryAction
{
    /**
     * @param  array<string, mixed>|null  $beforeState
     * @param  array<string, mixed>|null  $afterState
     */
    public function handle(
        CallOffRequest $request,
        User $actor,
        CallOffHistoryEventType $eventType,
        ?CallOffRequestStatus $previousStatus,
        ?CallOffRequestStatus $newStatus,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $customerResponse = null,
        ?string $internalReason = null,
        ?CallOffBatchOperation $operation = null,
        ?Carbon $performedAt = null,
    ): CallOffStatusHistory {
        $request->loadMissing('batch');

        $nextSequence = ((int) $request->histories()->lockForUpdate()->max('sequence')) + 1;
        $performedAt ??= now();

        return CallOffStatusHistory::query()->create([
            'call_off_request_id' => $request->id,
            'call_off_batch_id' => $request->call_off_batch_id,
            'call_off_batch_operation_id' => $operation?->id,
            'performed_by_user_id' => $actor->id,
            'sequence' => $nextSequence,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'customer_response' => $customerResponse,
            'internal_reason' => $internalReason,
            'performed_at' => $performedAt,
        ]);
    }
}
