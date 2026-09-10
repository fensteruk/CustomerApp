<?php

namespace App\Services;

use App\Data\PlotOverview;
use App\Data\PlotServiceOverview;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PlotOverallStatus;
use App\Enums\PlotServicePresentationState;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Bounded, customer-facing projection of an assigned site's plots.
 *
 * It deliberately translates legacy request values once, rather than making Blade infer
 * status from source rows and historical call-off records.
 */
class PlotOverviewQueryService
{
    /** @param array{plot?: string, service?: string, status?: string, show_completed?: bool} $filters */
    public function paginate(Site $site, array $filters): LengthAwarePaginator
    {
        $query = ProjectedPlot::query()
            ->where('site_id', $site->id)
            ->with([
                'services:id,projected_plot_id,service_identifier,source_completed_at,source_completion_observed_at,source_present,source_missing_since',
                'callOffRequests' => fn ($requestQuery) => $requestQuery
                    ->select([
                        'id',
                        'call_off_batch_id',
                        'projected_plot_id',
                        'projected_plot_service_id',
                        'service_identifier',
                        'requested_date',
                        'agreed_date',
                        'status',
                        'trashed_at',
                    ])
                    ->whereNull('trashed_at')
                    ->with('batch:id,service_identifier,requested_date'),
            ])
            ->when(filled($filters['plot'] ?? null), fn (Builder $builder): Builder => $builder->where('plot_reference', 'like', '%'.$filters['plot'].'%'));

        $service = CallOffServiceType::tryFrom((string) ($filters['service'] ?? ''));
        $state = PlotServicePresentationState::tryFrom((string) ($filters['status'] ?? ''));

        if ($service !== null || $state !== null) {
            $query->whereHas('services', function (Builder $serviceQuery) use ($service, $state): void {
                if ($service !== null) {
                    $serviceQuery->where('service_identifier', $service->value);
                }

                if ($state !== null) {
                    $this->applyStateConstraint($serviceQuery, $state);
                } elseif ($service !== null) {
                    $this->applyServiceActivityConstraint($serviceQuery);
                }
            });
        }

        if (! ($filters['show_completed'] ?? false)) {
            $query->where(function (Builder $builder): void {
                $builder
                    ->has('services', '<', count(CallOffServiceType::cases()))
                    ->orHas('services', '>', count(CallOffServiceType::cases()))
                    ->orWhereHas('services', fn (Builder $serviceQuery): Builder => $serviceQuery
                        ->whereNull('source_completed_at')
                        ->whereNull('source_completion_observed_at'));
            });
        }

        return $query
            ->orderBy('plot_reference')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProjectedPlot $plot): PlotOverview => $this->present($plot));
    }

    public function present(ProjectedPlot $plot): PlotOverview
    {
        $serviceRows = $plot->services->keyBy(fn ($service): string => $service->service_identifier->value);
        $serviceOverviews = [];

        foreach (CallOffServiceType::cases() as $service) {
            $serviceRow = $serviceRows->get($service->value);
            $request = $plot->callOffRequests
                ->filter(fn (CallOffRequest $request): bool => $request->effectiveServiceIdentifier() === $service && $request->status->isConflictActive())
                ->sortByDesc('id')
                ->first();

            if ($serviceRow?->isSourceCompleted()) {
                $serviceOverviews[$service->value] = new PlotServiceOverview(
                    service: $service,
                    state: PlotServicePresentationState::Completed,
                    date: $serviceRow->source_completed_at,
                );

                continue;
            }

            if ($request !== null && in_array($request->status, [CallOffRequestStatus::Approved, CallOffRequestStatus::DateAgreed], true)) {
                $serviceOverviews[$service->value] = new PlotServiceOverview(
                    service: $service,
                    state: PlotServicePresentationState::DateAgreed,
                    date: $request->agreed_date ?? $request->requested_date ?? $request->batch?->requested_date,
                );

                continue;
            }

            $serviceOverviews[$service->value] = new PlotServiceOverview(
                service: $service,
                state: $request === null ? PlotServicePresentationState::NotCalledOff : ($request->status === CallOffRequestStatus::AmendmentOnHold ? PlotServicePresentationState::OnHold : PlotServicePresentationState::AwaitingDate),
            );
        }

        return new PlotOverview(
            plot: $plot,
            services: $serviceOverviews,
            overallStatus: $this->overallStatus($serviceOverviews),
        );
    }

    /** @param array<string, PlotServiceOverview> $services */
    private function overallStatus(array $services): PlotOverallStatus
    {
        $states = array_map(fn (PlotServiceOverview $service): PlotServicePresentationState => $service->state, $services);

        if (count(array_filter($states, fn (PlotServicePresentationState $state): bool => $state === PlotServicePresentationState::Completed)) === count(CallOffServiceType::cases())) {
            return PlotOverallStatus::FullyCompleted;
        }

        if (in_array(PlotServicePresentationState::Completed, $states, true)) {
            return PlotOverallStatus::PartiallyCompleted;
        }

        // Preserve the pre-Sprint-3F aggregate treatment of AmendmentOnHold as
        // unresolved work; source completion still has higher precedence.
        if (in_array(PlotServicePresentationState::AwaitingDate, $states, true) || in_array(PlotServicePresentationState::OnHold, $states, true)) {
            return PlotOverallStatus::CallOffsInProgress;
        }

        if (in_array(PlotServicePresentationState::DateAgreed, $states, true)) {
            return PlotOverallStatus::DatesAgreed;
        }

        return PlotOverallStatus::NothingCalledOff;
    }

    private function applyServiceActivityConstraint(Builder $query): void
    {
        $query->where(function (Builder $serviceQuery): void {
            $serviceQuery
                ->whereNotNull('source_completed_at')
                ->orWhereNotNull('source_completion_observed_at')
                ->orWhereHas('callOffRequests', fn (Builder $requests): Builder => $requests
                    ->whereNull('trashed_at')
                    ->whereIn('status', $this->conflictActiveStatuses()));
        });
    }

    private function applyStateConstraint(Builder $query, PlotServicePresentationState $state): void
    {
        match ($state) {
            PlotServicePresentationState::Completed => $query->where(function (Builder $serviceQuery): void {
                $serviceQuery->whereNotNull('source_completed_at')->orWhereNotNull('source_completion_observed_at');
            }),
            PlotServicePresentationState::DateAgreed => $query
                ->whereNull('source_completed_at')
                ->whereNull('source_completion_observed_at')
                ->whereHas('callOffRequests', fn (Builder $requests): Builder => $requests
                    ->whereNull('trashed_at')
                    ->whereIn('status', [CallOffRequestStatus::Approved->value, CallOffRequestStatus::DateAgreed->value])),
            PlotServicePresentationState::OnHold => $query
                ->whereNull('source_completed_at')
                ->whereNull('source_completion_observed_at')
                ->whereHas('callOffRequests', fn (Builder $requests): Builder => $requests
                    ->whereNull('trashed_at')
                    ->where('status', CallOffRequestStatus::AmendmentOnHold->value)),
            PlotServicePresentationState::AwaitingDate => $query
                ->whereNull('source_completed_at')
                ->whereNull('source_completion_observed_at')
                ->whereHas('callOffRequests', fn (Builder $requests): Builder => $requests
                    ->whereNull('trashed_at')
                    ->whereIn('status', [
                        CallOffRequestStatus::Submitted->value,
                        CallOffRequestStatus::AwaitingFenster->value,
                        CallOffRequestStatus::AwaitingSiteUser->value,
                    ])),
            PlotServicePresentationState::NotCalledOff => $query
                ->whereNull('source_completed_at')
                ->whereNull('source_completion_observed_at')
                ->whereDoesntHave('callOffRequests', fn (Builder $requests): Builder => $requests
                    ->whereNull('trashed_at')
                    ->whereIn('status', $this->conflictActiveStatuses())),
        };
    }

    /** @return array<int, string> */
    private function conflictActiveStatuses(): array
    {
        return array_map(
            fn (CallOffRequestStatus $status): string => $status->value,
            array_filter(CallOffRequestStatus::cases(), fn (CallOffRequestStatus $status): bool => $status->isConflictActive()),
        );
    }
}
