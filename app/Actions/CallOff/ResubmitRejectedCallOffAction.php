<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Models\CallOffRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResubmitRejectedCallOffAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly SubmitCallOffBatchAction $submitBatch = new SubmitCallOffBatchAction,
        private readonly RecordCallOffStatusHistoryAction $recordHistory = new RecordCallOffStatusHistoryAction,
    ) {}

    public function handle(
        User $actor,
        CallOffRequest $sourceRequest,
        ?string $customerResponse = null,
        Carbon|string|null $requestedDate = null,
    ): CallOffRequest {
        return DB::transaction(function () use ($actor, $sourceRequest, $requestedDate, $customerResponse): CallOffRequest {
            $sourceRequest = CallOffRequest::query()->whereKey($sourceRequest->id)->lockForUpdate()->firstOrFail();
            $sourceRequest->loadMissing('batch', 'projectedPlot');
            $this->eligibility->ensureCanResubmit($actor, $sourceRequest);

            $batch = $this->submitBatch->handle(
                user: $actor,
                site: $sourceRequest->batch->site,
                serviceType: $sourceRequest->batch->service_identifier,
                requestedDate: $requestedDate ?? $sourceRequest->batch->requested_date,
                projectedPlots: [$sourceRequest->projectedPlot],
                customerResponse: $customerResponse,
            );

            $newRequest = $batch->requests()->firstOrFail();
            $newRequest->forceFill(['resubmitted_from_call_off_request_id' => $sourceRequest->id])->save();
            $newRequest->refresh();

            $this->recordHistory->handle(
                request: $newRequest,
                actor: $actor,
                eventType: CallOffHistoryEventType::Resubmitted,
                previousStatus: $newRequest->status,
                newStatus: $newRequest->status,
                beforeState: $sourceRequest->stateSnapshot(),
                afterState: $newRequest->stateSnapshot(),
                customerResponse: $customerResponse,
            );

            return $newRequest;
        });
    }
}
