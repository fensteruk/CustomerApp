<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Events\CallOffSubmitted;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffLeadTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitMultiCallOffBatchAction
{
    public function __construct(private readonly DetermineCallOffEligibilityAction $eligibility, private readonly UpdateConflictKeyAction $conflicts, private readonly RecordCallOffStatusHistoryAction $history, private readonly CallOffLeadTimeService $leadTimes) {}

    /** @param array<int, array{plot_service_id:int, requested_date:string, early_date_reason:?string}> $items */
    public function handle(User $user, Site $site, array $items, ?string $customerResponse = null): CallOffBatch
    {
        if ($items === []) {
            throw ValidationException::withMessages(['combinations' => 'Select at least one eligible plot and service combination.']);
        }

        $batch = DB::transaction(function () use ($user, $site, $items, $customerResponse): CallOffBatch {
            $services = ProjectedPlotService::query()->with('projectedPlot.products')->whereKey(collect($items)->pluck('plot_service_id'))->lockForUpdate()->get()->keyBy('id');
            if ($services->count() !== count($items)) {
                throw ValidationException::withMessages(['combinations' => 'One or more selected combinations are no longer available.']);
            }

            $first = $services->firstOrFail();
            $batch = CallOffBatch::query()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id, 'service_identifier' => $first->service_identifier, 'requested_date' => $items[0]['requested_date'], 'customer_response' => $customerResponse, 'submitted_at' => now()]);

            foreach ($items as $item) {
                $service = $services->get($item['plot_service_id']);
                $date = CarbonImmutable::parse($item['requested_date'])->startOfDay();
                $this->eligibility->ensureCanSubmitBatch($user, $site, $service->service_identifier, [$service->projectedPlot]);
                $earliest = $this->leadTimes->earliestNormalDate($service);
                if (! $this->leadTimes->isPermittedRequestedDate($date)) {
                    throw ValidationException::withMessages(['combinations' => 'Each requested date must be a weekday within six months.']);
                }
                $early = $date->lt($earliest);
                if ($early && blank($item['early_date_reason'])) {
                    throw ValidationException::withMessages(['combinations' => 'Explain each Request Earlier Date exception.']);
                }

                $request = CallOffRequest::query()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id, 'projected_plot_service_id' => $service->id, 'service_identifier' => $service->service_identifier, 'requested_date' => $date, 'normal_earliest_date' => $earliest, 'is_early_date_exception' => $early, 'early_date_reason' => $early ? $item['early_date_reason'] : null, 'customer_response' => $customerResponse, 'status' => CallOffRequestStatus::AwaitingFenster]);
                $before = $request->stateSnapshot();
                $this->conflicts->handle($request);
                $request->refresh();
                $this->history->handle($request, $user, CallOffHistoryEventType::DateRequested, null, CallOffRequestStatus::AwaitingFenster, $before, $request->stateSnapshot(), $customerResponse);
            }

            return $batch->load('requests');
        });

        foreach ($batch->requests as $request) {
            event(new CallOffSubmitted($request->id));
        }

        return $batch;
    }
}
