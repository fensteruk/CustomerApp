<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffOperationType;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffBatchOperationItem;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuickUndoCallOffOperationAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $recordHistory = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(User $actor, CallOffBatchOperation $operation): CallOffBatchOperation
    {
        return DB::transaction(function () use ($actor, $operation): CallOffBatchOperation {
            $operation = CallOffBatchOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->ensureCanUndo($actor, $operation);
            $operation->load('items');

            $requestIds = $operation->items->pluck('call_off_request_id')->all();
            $requests = CallOffRequest::query()->whereKey($requestIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($operation->items as $item) {
                $request = $requests->get($item->call_off_request_id);

                if ($request === null || ! $this->statesMatch($request->stateSnapshot(), $item->after_state)) {
                    throw ValidationException::withMessages(['operation' => 'This operation can no longer be undone because a request has changed.']);
                }

                $restoredConflictKey = $item->before_state['active_conflict_key'] ?? null;
                if ($restoredConflictKey !== null && CallOffRequest::query()->where('active_conflict_key', $restoredConflictKey)->whereKeyNot($request->id)->exists()) {
                    throw ValidationException::withMessages(['operation' => 'This operation can no longer be undone because a duplicate active request exists.']);
                }
            }

            $undoOperation = CallOffBatchOperation::query()->create([
                'call_off_batch_id' => $operation->call_off_batch_id,
                'performed_by_user_id' => $actor->id,
                'operation_type' => CallOffOperationType::QuickUndo,
                'performed_at' => now(),
                'undo_expires_at' => null,
            ]);

            foreach ($operation->items as $item) {
                $request = $requests->get($item->call_off_request_id);
                $beforeState = $request->stateSnapshot();
                $previousStatus = $request->status;
                $targetState = $item->before_state;

                $request->forceFill([
                    'status' => $targetState['status'],
                    'trashed_at' => $targetState['trashed_at'],
                    'trash_expires_at' => $targetState['trash_expires_at'],
                ])->save();
                $this->updateConflictKey->handle($request);
                $request->refresh();

                CallOffBatchOperationItem::query()->create([
                    'call_off_batch_operation_id' => $undoOperation->id,
                    'call_off_request_id' => $request->id,
                    'before_state' => $beforeState,
                    'after_state' => $request->stateSnapshot(),
                ]);

                $this->recordHistory->handle(
                    $request,
                    $actor,
                    CallOffHistoryEventType::UndoApplied,
                    $previousStatus,
                    CallOffRequestStatus::from($targetState['status']),
                    $beforeState,
                    $request->stateSnapshot(),
                    operation: $undoOperation,
                );
            }

            $operation->forceFill(['reversed_by_operation_id' => $undoOperation->id])->save();

            return $undoOperation->load('items');
        });
    }

    /**
     * JSON object member ordering is not stable across supported database engines.
     * Preserve strict value comparison while normalising the flat audit snapshot keys.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $recorded
     */
    private function statesMatch(array $current, array $recorded): bool
    {
        ksort($current);
        ksort($recorded);

        return $current === $recorded;
    }
}
