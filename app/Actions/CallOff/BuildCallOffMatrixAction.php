<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffServiceType;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffLeadTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BuildCallOffMatrixAction
{
    public function __construct(private readonly DetermineCallOffEligibilityAction $eligibility, private readonly CallOffLeadTimeService $leadTimes) {}

    /** @param array<int,string> $plotUuids @param array<string,string> $serviceDates @param array<int,string> $excludedKeys @param array<string,string> $earlyReasons */
    public function handle(User $user, Site $site, array $plotUuids, array $serviceDates, array $excludedKeys = [], array $earlyReasons = [], bool $requireEarlyReasons = true): array
    {
        $plots = ProjectedPlot::query()->where('site_id', $site->id)->whereIn('uuid', $plotUuids)->with(['services', 'products'])->get()->keyBy('uuid');
        if ($plots->count() !== count(array_unique($plotUuids))) {
            throw ValidationException::withMessages(['plots' => 'One or more selected plots are not available for this site.']);
        }
        $plots->each(fn (ProjectedPlot $plot) => $plot->services->each(fn ($service) => $service->setRelation('projectedPlot', $plot)));
        $serviceTypes = collect(array_keys($serviceDates))->map(fn (string $service): CallOffServiceType => CallOffServiceType::from($service));
        $this->eligibility->ensureCanSubmitForSite($user, $site);
        $conflictKeys = $plots->flatMap(fn (ProjectedPlot $plot): array => $serviceTypes->map(fn (CallOffServiceType $service): string => UpdateConflictKeyAction::keyFor((int) $plot->id, $service->value))->all());
        $activeConflictKeys = CallOffRequest::query()->whereIn('active_conflict_key', $conflictKeys)->pluck('active_conflict_key')->flip();
        $rows = [];
        foreach ($plotUuids as $plotUuid) {
            $plot = $plots->get($plotUuid);
            foreach (CallOffServiceType::cases() as $service) {
                if (! array_key_exists($service->value, $serviceDates)) {
                    continue;
                }
                $key = $plotUuid.'|'.$service->value;
                $projection = $plot->services->firstWhere('service_identifier', $service);
                $included = ! in_array($key, $excludedKeys, true);
                $reason = $this->eligibility->unavailableReasonForProjection(
                    $plot,
                    $projection,
                    $site,
                    $service,
                    $activeConflictKeys->has(UpdateConflictKeyAction::keyFor((int) $plot->id, $service->value)),
                    true,
                );
                if ($reason !== null) {
                    $included = false;
                }
                $date = CarbonImmutable::parse($serviceDates[$service->value])->startOfDay();
                $earliest = $projection === null ? null : $this->leadTimes->earliestNormalDate($projection);
                $early = $earliest !== null && $date->lt($earliest);
                if ($included && ! $this->leadTimes->isPermittedRequestedDate($date)) {
                    throw ValidationException::withMessages(['service_dates' => 'Requested dates must be weekdays within six months.']);
                }
                if ($included && $early && $requireEarlyReasons && blank($earlyReasons[$key] ?? null)) {
                    throw ValidationException::withMessages(['early_reasons.'.$key => 'Explain this earlier-date request.']);
                }
                $rows[] = ['key' => $key, 'plot_uuid' => $plot->uuid, 'plot_reference' => $plot->plot_reference, 'service' => $service->value, 'requested_date' => $date->toDateString(), 'included' => $included, 'available' => $reason === null, 'reason' => $reason, 'normal_earliest_date' => $earliest?->toDateString(), 'is_early_exception' => $early, 'early_reason' => $early ? ($earlyReasons[$key] ?? null) : null, 'products' => $plot->products->filter->hasPositiveQuantity()->map(fn ($product) => ['code' => $product->product_code, 'quantity' => $product->quantity])->values()->all(), 'plot_service_id' => $projection?->id];
            }
        }

        return $rows;
    }
}
