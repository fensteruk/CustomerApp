<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffOperationType;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffBatchOperationItem;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawCallOffRequestsAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $recordHistory = new RecordCallOffStatusHistoryAction,
    ) {}

    /**
     * @param  iterable<int, CallOffRequest>  $requests
     */
    public function handle(User $actor, iterable $requests, ?string $customerResponse = null): CallOffBatchOperation
    {
        return DB::transaction(function () use ($actor, $requests, $customerResponse): CallOffBatchOperation {
            $lockedRequests = $this->lockRequests($requests);
            $batchId = $this->singleBatchId($lockedRequests);

            foreach ($lockedRequests as $request) {
                $this->eligibility->ensureCanWithdraw($actor, $request);
            }

            $operation = $this->createOperation($actor, $batchId, CallOffOperationType::Withdrawal);

            foreach ($lockedRequests as $request) {
                $beforeState = $request->stateSnapshot();
                $previousStatus = $request->status;
                $request->status = CallOffRequestStatus::Withdrawn;
                $request->save();
                $this->updateConflictKey->handle($request);
                $request->refresh();
                $afterState = $request->stateSnapshot();

                $this->createItem($operation, $request, $beforeState, $afterState);
                $this->recordHistory->handle($request, $actor, CallOffHistoryEventType::Withdrawn, $previousStatus, CallOffRequestStatus::Withdrawn, $beforeState, $afterState, $customerResponse, null, $operation);
            }

            return $operation->load('items');
        });
    }

    /**
     * @param  iterable<int, CallOffRequest>  $requests
     * @return Collection<int, CallOffRequest>
     */
    private function lockRequests(iterable $requests): Collection
    {
        $ids = Collection::make($requests)->pluck('id')->values();

        if ($ids->isEmpty() || $ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages(['requests' => 'Each call-off request may only be selected once.']);
        }

        $lockedRequests = CallOffRequest::query()->whereKey($ids->all())->lockForUpdate()->get();

        if ($lockedRequests->count() !== $ids->count()) {
            throw ValidationException::withMessages(['requests' => 'One or more selected call-off requests no longer exist.']);
        }

        return $lockedRequests;
    }

    /**
     * @param  Collection<int, CallOffRequest>  $requests
     */
    private function singleBatchId(Collection $requests): int
    {
        if ($requests->isEmpty() || $requests->pluck('call_off_batch_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['requests' => 'Batch operations must target requests from one batch.']);
        }

        return (int) $requests->first()->call_off_batch_id;
    }

    private function createOperation(User $actor, int $batchId, CallOffOperationType $type): CallOffBatchOperation
    {
        return CallOffBatchOperation::query()->create([
            'call_off_batch_id' => $batchId,
            'performed_by_user_id' => $actor->id,
            'operation_type' => $type,
            'performed_at' => now(),
            'undo_expires_at' => now()->addSeconds(5),
        ]);
    }

    /**
     * @param  array<string, mixed>  $beforeState
     * @param  array<string, mixed>  $afterState
     */
    private function createItem(CallOffBatchOperation $operation, CallOffRequest $request, array $beforeState, array $afterState): void
    {
        CallOffBatchOperationItem::query()->create([
            'call_off_batch_operation_id' => $operation->id,
            'call_off_request_id' => $request->id,
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);
    }
}
