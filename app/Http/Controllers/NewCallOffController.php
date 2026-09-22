<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\BuildCallOffMatrixAction;
use App\Enums\CallOffServiceType;
use App\Http\Requests\BuildCallOffMatrixRequest;
use App\Http\Requests\DashboardCallOffSelectionRequest;
use App\Http\Requests\SubmitCallOffConfirmationRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Services\CallOffLeadTimeService;
use App\Services\CallOffSubmissionWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewCallOffController extends Controller
{
    public function create(Request $request, CallOffLeadTimeService $leadTimes): View
    {
        $site = $this->activeSite($request);
        Gate::authorize('submit-call-off', $site);

        $selected = $request->validate([
            'plots' => ['nullable', 'array'],
            'plots.*' => ['required', 'string', 'uuid', 'distinct'],
        ])['plots'] ?? [];
        $this->ensurePlotsBelongToSite($site, $selected);

        return view('portal.call-offs.create', [
            'activeSite' => $site->load('customerOrganisation'),
            'serviceTypes' => CallOffServiceType::cases(),
            'plots' => $site->projectedPlots()->orderBy('plot_reference')->get(['uuid', 'plot_reference', 'is_completed']),
            'selectedPlotUuids' => $selected,
            'cavityEarliestDate' => $leadTimes->earliestCavityCloserDate(),
        ]);
    }

    public function cavityCloserDate(Request $request, CallOffLeadTimeService $leadTimes): JsonResponse
    {
        Gate::authorize('submit-call-off', $this->activeSite($request));
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $selected = CarbonImmutable::parse($data['date'])->startOfDay();
        $earliest = $leadTimes->earliestCavityCloserDate();

        return response()->json([
            'selected_date' => $selected->toDateString(),
            'selected_display' => $selected->format('l j F Y'),
            'earliest_date' => $earliest->toDateString(),
            'earliest_display' => $earliest->format('l j F Y'),
            'working_days_early' => $selected->lt($earliest) ? $leadTimes->workingDaysEarly($selected, $earliest) : 0,
            'is_early' => $selected->lt($earliest),
            'is_permitted' => $leadTimes->isPermittedRequestedDate($selected),
        ])->header('Cache-Control', 'no-store');
    }

    public function dashboardSelection(DashboardCallOffSelectionRequest $request): RedirectResponse
    {
        $site = $this->activeSite($request);
        $plots = $request->validated('plots');
        $this->ensurePlotsBelongToSite($site, $plots);

        return redirect()->route('portal.call-offs.create', ['plots' => $plots]);
    }

    public function matrix(BuildCallOffMatrixRequest $request, BuildCallOffMatrixAction $matrix): View|RedirectResponse
    {
        $site = $this->activeSite($request);
        $data = $request->validated();

        try {
            $rows = $matrix->handle($request->user(), $site, $data['plots'], $data['service_dates'], requireEarlyReasons: false);
            if (collect($rows)->contains(fn (array $row): bool => $row['included'] && $row['service'] === CallOffServiceType::CavityClosers->value && $row['is_early_exception'])
                && blank($data['cavity_early_reason'] ?? null)) {
                throw ValidationException::withMessages(['cavity_early_reason' => 'Give an Early Date Reason for Cavity Closers.']);
            }
        } catch (ValidationException $exception) {
            return redirect()->route('portal.call-offs.create')->withErrors($exception->errors())->withInput();
        }

        return view('portal.call-offs.matrix', [
            'activeSite' => $site,
            'rows' => $rows,
            'plots' => $data['plots'],
            'serviceDates' => $data['service_dates'],
            'customerResponse' => $data['customer_response'] ?? '',
            'cavityEarlyReason' => $data['cavity_early_reason'] ?? null,
        ]);
    }

    public function review(BuildCallOffMatrixRequest $request, CallOffSubmissionWorkflow $workflow): View|RedirectResponse
    {
        $site = $this->activeSite($request);
        $data = $request->validated();

        try {
            $payload = $workflow->review(
                $request->user(),
                $site,
                $data['plots'],
                $data['service_dates'],
                $data['excluded'] ?? [],
                $data['early_reasons'] ?? [],
                $data['customer_response'] ?? null,
                $request->session(),
            );
        } catch (ValidationException $exception) {
            return redirect()->route('portal.call-offs.create')->withErrors($exception->errors())->withInput();
        }

        return view('portal.call-offs.confirm', [
            'activeSite' => $site,
            'review' => $this->customerSafeReview($payload),
            'confirmationSignature' => $payload['signature'],
        ]);
    }

    public function store(SubmitCallOffConfirmationRequest $request, CallOffSubmissionWorkflow $workflow): RedirectResponse
    {
        $site = $this->activeSite($request);

        try {
            $batch = $workflow->submit($request->user(), $site, (string) $request->validated('confirmation_signature'), $request->session());
        } catch (ValidationException $exception) {
            return redirect()->route('portal.call-offs.create')->withErrors($exception->errors());
        }

        $requestCount = $batch->requests->count();
        $plotCount = $batch->requests->pluck('projected_plot_id')->unique()->count();

        return redirect()
            ->route('portal.site-dashboard')
            ->with('status', 'Call-off submitted for '.$plotCount.' '.str('plot')->plural($plotCount).' / '.$requestCount.' service '.str('request')->plural($requestCount).'.');
    }

    private function activeSite(Request $request): Site
    {
        return $request->attributes->get('activeSite');
    }

    /** @param array<int, string> $uuids */
    private function ensurePlotsBelongToSite(Site $site, array $uuids): void
    {
        if (ProjectedPlot::query()->where('site_id', $site->id)->whereIn('uuid', $uuids)->count() !== count($uuids)) {
            throw ValidationException::withMessages([
                'plots' => 'One or more selected plots are not available for this site.',
            ]);
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function customerSafeReview(array $payload): array
    {
        return [
            'message' => $payload['message'],
            'request_count' => $payload['request_count'],
            'rows' => collect($payload['rows'])->map(fn (array $row): array => collect($row)->except(['plot_service_id'])->all())->all(),
        ];
    }
}
