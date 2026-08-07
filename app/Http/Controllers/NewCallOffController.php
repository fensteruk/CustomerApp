<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\DetermineCallOffEligibilityAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffServiceType;
use App\Http\Requests\NewCallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewCallOffController extends Controller
{
    public const CONFIRMATION_SIGNATURE_SESSION_KEY = 'call_off_confirmation_signature';

    public function create(Request $request, DetermineCallOffEligibilityAction $eligibility): View
    {
        $activeSite = $request->attributes->get('activeSite')->load('customerOrganisation');

        return view('portal.call-offs.create', [
            'activeSite' => $activeSite,
            'serviceTypes' => CallOffServiceType::cases(),
            'eligiblePlotsByService' => $this->eligiblePlotsByService($request, $activeSite, $eligibility),
        ]);
    }

    public function confirm(NewCallOffRequest $request, DetermineCallOffEligibilityAction $eligibility): View|RedirectResponse
    {
        $activeSite = $request->attributes->get('activeSite')->load('customerOrganisation');

        try {
            $selectedPlots = $this->selectedProjectedPlots($request, $activeSite);
            $eligibility->ensureCanSubmitBatch($request->user(), $activeSite, $request->serviceType(), $selectedPlots);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('portal.call-offs.create')
                ->withErrors($exception->errors())
                ->withInput();
        }

        $confirmationPayload = $this->confirmationPayload($request);
        $confirmationSignature = $this->confirmationSignature($confirmationPayload);

        $request->session()->put(self::CONFIRMATION_SIGNATURE_SESSION_KEY, $confirmationSignature);

        return view('portal.call-offs.confirm', [
            'activeSite' => $activeSite,
            'serviceType' => $request->serviceType(),
            'requestedDate' => $request->date('requested_date'),
            'customerResponse' => $request->validated('customer_response'),
            'selectedPlots' => $selectedPlots,
            'formData' => $confirmationPayload,
            'confirmationSignature' => $confirmationSignature,
        ]);
    }

    public function store(NewCallOffRequest $request, SubmitCallOffBatchAction $submitCallOffBatch): RedirectResponse
    {
        $activeSite = $request->attributes->get('activeSite');

        try {
            $this->ensureConfirmedPayload($request);

            $selectedPlots = $this->selectedProjectedPlots($request, $activeSite);

            $batch = $submitCallOffBatch->handle(
                user: $request->user(),
                site: $activeSite,
                serviceType: $request->serviceType(),
                requestedDate: $request->validated('requested_date'),
                projectedPlots: $selectedPlots,
                customerResponse: $request->validated('customer_response'),
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('portal.call-offs.create')
                ->withErrors($exception->errors())
                ->withInput();
        }

        $request->session()->forget(self::CONFIRMATION_SIGNATURE_SESSION_KEY);

        $requestCount = $batch->requests->count();

        return redirect()
            ->route('portal.site-dashboard')
            ->with('status', 'Call-off submitted for '.$requestCount.' projected '.str('plot')->plural($requestCount).'.');
    }

    /**
     * @return array<string, Collection<int, ProjectedPlot>>
     */
    private function eligiblePlotsByService(Request $request, Site $site, DetermineCallOffEligibilityAction $eligibility): array
    {
        $plots = $site->projectedPlots()
            ->outstanding()
            ->orderBy('plot_reference')
            ->get(['id', 'uuid', 'site_id', 'plot_reference', 'is_completed']);

        $eligiblePlots = [];

        foreach (CallOffServiceType::cases() as $serviceType) {
            $eligiblePlots[$serviceType->value] = $plots
                ->filter(function (ProjectedPlot $plot) use ($request, $site, $serviceType, $eligibility): bool {
                    try {
                        $eligibility->ensureCanSubmitBatch($request->user(), $site, $serviceType, [$plot]);
                    } catch (ValidationException) {
                        return false;
                    }

                    return true;
                })
                ->values();
        }

        return $eligiblePlots;
    }

    /**
     * @return Collection<int, ProjectedPlot>
     */
    private function selectedProjectedPlots(NewCallOffRequest $request, Site $site): Collection
    {
        $selectedUuids = Collection::make($request->validated('projected_plots'))
            ->map(fn (string $uuid): string => trim($uuid))
            ->values();

        $plots = ProjectedPlot::query()
            ->where('site_id', $site->id)
            ->whereIn('uuid', $selectedUuids->all())
            ->get(['id', 'uuid', 'site_id', 'plot_reference', 'is_completed'])
            ->keyBy('uuid');

        if ($plots->count() !== $selectedUuids->count()) {
            throw ValidationException::withMessages([
                'projected_plots' => 'One or more selected projected plots are not available for this site.',
            ]);
        }

        return $selectedUuids
            ->map(fn (string $uuid): ProjectedPlot => $plots->get($uuid))
            ->values();
    }

    /**
     * @return array{service_identifier: string, requested_date: string, customer_response: string, projected_plots: array<int, string>}
     */
    private function confirmationPayload(NewCallOffRequest $request): array
    {
        $validated = $request->validated();

        return [
            'service_identifier' => (string) $validated['service_identifier'],
            'requested_date' => (string) $validated['requested_date'],
            'customer_response' => (string) ($validated['customer_response'] ?? ''),
            'projected_plots' => Collection::make($validated['projected_plots'])
                ->map(fn (string $uuid): string => trim($uuid))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{service_identifier: string, requested_date: string, customer_response: string, projected_plots: array<int, string>}  $payload
     */
    private function confirmationSignature(array $payload): string
    {
        return hash_hmac(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );
    }

    private function ensureConfirmedPayload(NewCallOffRequest $request): void
    {
        $expectedSignature = $request->session()->get(self::CONFIRMATION_SIGNATURE_SESSION_KEY);
        $providedSignature = (string) $request->input('confirmation_signature', '');
        $actualSignature = $this->confirmationSignature($this->confirmationPayload($request));

        if (! is_string($expectedSignature) || ! hash_equals($expectedSignature, $providedSignature) || ! hash_equals($expectedSignature, $actualSignature)) {
            throw ValidationException::withMessages([
                'request' => 'Review the call-off details again before submitting.',
            ]);
        }
    }
}
