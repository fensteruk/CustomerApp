<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Events\CallOffSubmitted;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
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

    /** @param array<int, array{plot_service_id:?int, plot_uuid?:string, service?:string, requested_date:string, early_date_reason:?string}> $items */
    public function handle(User $user, Site $site, array $items, ?string $customerResponse = null): CallOffBatch
    {
        if ($items === []) {
            throw ValidationException::withMessages(['combinations' => 'Select at least one eligible plot and service combination.']);
        }

        $batch = DB::transaction(function () use ($user, $site, $items, $customerResponse): CallOffBatch {
            $serviceIds = collect($items)->pluck('plot_service_id')->filter()->unique()->values();
            $services = ProjectedPlotService::query()->with('projectedPlot.products')->whereKey($serviceIds)->lockForUpdate()->get()->keyBy('id');
            if ($services->count() !== $serviceIds->count()) {
                throw ValidationException::withMessages(['combinations' => 'One or more selected combinations are no longer available.']);
            }

            $missing = collect($items)->filter(fn (array $item): bool => $item['plot_service_id'] === null);
            if ($missing->contains(fn (array $item): bool => ($item['service'] ?? null) !== CallOffServiceType::CavityClosers->value || empty($item['plot_uuid']))) {
                throw ValidationException::withMessages(['combinations' => 'One or more selected combinations are no longer available.']);
            }
            $plots = ProjectedPlot::query()->where('site_id', $site->id)->whereIn('uuid', $missing->pluck('plot_uuid'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
            if ($plots->count() !== $missing->pluck('plot_uuid')->unique()->count()) {
                throw ValidationException::withMessages(['combinations' => 'One or more selected plots are no longer available.']);
            }

            $this->eligibility->ensureCanSubmitForSite($user, $site);
            $resolved = [];
            foreach ($items as $index => $item) {
                $service = $item['plot_service_id'] === null
                    ? $plots->get($item['plot_uuid'])->services()->firstOrCreate(
                        ['service_identifier' => CallOffServiceType::CavityClosers->value], ['source_present' => false],
                    )
                    : $services->get($item['plot_service_id']);
                if ($service === null || (isset($item['plot_uuid']) && $service->projectedPlot->uuid !== $item['plot_uuid'])
                    || (isset($item['service']) && $service->service_identifier->value !== $item['service'])) {
                    throw ValidationException::withMessages(['combinations' => 'One or more selected combinations are no longer available.']);
                }
                $resolved[$index] = $service;
            }
            if (collect($resolved)->pluck('id')->unique()->count() !== count($items)) {
                throw ValidationException::withMessages(['combinations' => 'Select each plot and service only once.']);
            }

            $first = $resolved[0];
            $batch = CallOffBatch::query()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id, 'service_identifier' => $first->service_identifier, 'requested_date' => $items[0]['requested_date'], 'customer_response' => $customerResponse, 'submitted_at' => now()]);

            foreach ($items as $index => $item) {
                $service = $resolved[$index];
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
                $after = $request->stateSnapshot();
                if ($service->service_identifier === CallOffServiceType::CavityClosers) {
                    $after += ['lead_time_working_days' => CallOffLeadTimeService::CAVITY_CLOSER_WORKING_DAYS,
                        'normal_earliest_date' => $earliest->toDateString(), 'early_date_reason' => $early ? $item['early_date_reason'] : null,
                        'working_days_early' => $early ? $this->leadTimes->workingDaysEarly($date, $earliest) : 0,
                        'actor_name' => $user->name, 'actor_role' => $user->portalRole?->name];
                }
                $this->history->handle($request, $user, CallOffHistoryEventType::DateRequested, null, CallOffRequestStatus::AwaitingFenster, $before, $after, $customerResponse);
            }

            return $batch->load('requests');
        });

        foreach ($batch->requests as $request) {
            event(new CallOffSubmitted($request->id));
        }

        return $batch;
    }
}
