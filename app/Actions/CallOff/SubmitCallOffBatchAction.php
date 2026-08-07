<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Events\CallOffSubmitted;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitCallOffBatchAction
{
    public function __construct(
        private readonly DetermineCallOffEligibilityAction $eligibility = new DetermineCallOffEligibilityAction,
        private readonly UpdateConflictKeyAction $updateConflictKey = new UpdateConflictKeyAction,
        private readonly RecordCallOffStatusHistoryAction $recordHistory = new RecordCallOffStatusHistoryAction,
    ) {}

    /**
     * @param  iterable<int, ProjectedPlot>  $projectedPlots
     */
    public function handle(
        User $user,
        Site $site,
        CallOffServiceType|string $serviceType,
        Carbon|string $requestedDate,
        iterable $projectedPlots,
        ?string $customerResponse = null,
    ): CallOffBatch {
        $serviceType = $serviceType instanceof CallOffServiceType
            ? $serviceType
            : CallOffServiceType::tryFrom($serviceType);

        if ($serviceType === null) {
            throw ValidationException::withMessages(['service_identifier' => 'The selected service is not supported.']);
        }
        $plots = Collection::make($projectedPlots)->values();

        $batch = DB::transaction(function () use ($user, $site, $serviceType, $requestedDate, $plots, $customerResponse): CallOffBatch {
            if ($plots->pluck('id')->unique()->count() !== $plots->count()) {
                throw ValidationException::withMessages(['projected_plots' => 'Each projected plot may only be selected once.']);
            }

            $lockedPlots = ProjectedPlot::query()
                ->whereKey($plots->pluck('id')->all())
                ->lockForUpdate()
                ->get();

            if ($lockedPlots->count() !== $plots->count()) {
                throw ValidationException::withMessages(['projected_plots' => 'One or more selected projected plots no longer exist.']);
            }

            $this->eligibility->ensureCanSubmitBatch($user, $site, $serviceType, $lockedPlots);

            $batch = CallOffBatch::query()->create([
                'site_id' => $site->id,
                'submitted_by_user_id' => $user->id,
                'service_identifier' => $serviceType,
                'requested_date' => $requestedDate,
                'customer_response' => $customerResponse,
                'submitted_at' => now(),
            ]);

            foreach ($lockedPlots as $plot) {
                $request = CallOffRequest::query()->create([
                    'call_off_batch_id' => $batch->id,
                    'projected_plot_id' => $plot->id,
                    'status' => CallOffRequestStatus::Submitted,
                ]);

                $beforeState = $request->stateSnapshot();
                $this->updateConflictKey->handle($request);
                $request->refresh();

                $this->recordHistory->handle(
                    request: $request,
                    actor: $user,
                    eventType: CallOffHistoryEventType::Submitted,
                    previousStatus: null,
                    newStatus: CallOffRequestStatus::Submitted,
                    beforeState: $beforeState,
                    afterState: $request->stateSnapshot(),
                    customerResponse: $customerResponse,
                );
            }

            return $batch->load('requests');
        });

        foreach ($batch->requests as $request) {
            event(new CallOffSubmitted($request->id));
        }

        return $batch;
    }
}
