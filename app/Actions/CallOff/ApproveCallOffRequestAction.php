<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffApproved;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveCallOffRequestAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $recordHistory = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(User $actor, CallOffRequest $request, ?string $customerResponse = null, ?string $internalReason = null): CallOffRequest
    {
        $approvedRequest = DB::transaction(function () use ($actor, $request, $customerResponse, $internalReason): CallOffRequest {
            $request = CallOffRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->ensureCanApprove($actor, $request);

            $beforeState = $request->stateSnapshot();
            $previousStatus = $request->status;
            $request->status = CallOffRequestStatus::Approved;
            $request->save();
            $this->updateConflictKey->handle($request);
            $request->refresh();

            $this->recordHistory->handle(
                $request,
                $actor,
                CallOffHistoryEventType::Approved,
                $previousStatus,
                CallOffRequestStatus::Approved,
                $beforeState,
                $request->stateSnapshot(),
                $customerResponse,
                $internalReason,
            );

            return $request;
        });

        event(new CallOffApproved($approvedRequest->id));

        return $approvedRequest;
    }
}
