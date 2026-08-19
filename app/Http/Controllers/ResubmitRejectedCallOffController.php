<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\ResubmitRejectedCallOffAction;
use App\Http\Requests\ResubmitRejectedCallOffRequest;
use App\Models\CallOffRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResubmitRejectedCallOffController extends Controller
{
    public const CONFIRMATION_SIGNATURE_SESSION_KEY = 'rejected_call_off_resubmission_signature';

    public function create(Request $request, CallOffRequest $callOffRequest): View
    {
        $sourceRequest = $this->authorisedSourceRequest($request, $callOffRequest);

        return view('portal.call-offs.resubmit.create', [
            'activeSite' => $request->attributes->get('activeSite'),
            'sourceRequest' => $sourceRequest,
        ]);
    }

    public function confirm(ResubmitRejectedCallOffRequest $request, CallOffRequest $callOffRequest): View|RedirectResponse
    {
        try {
            $sourceRequest = $this->authorisedSourceRequest($request, $callOffRequest);
        } catch (AuthorizationException) {
            return redirect()->route('portal.site-dashboard')
                ->withErrors(['request' => 'You are no longer authorised to resubmit this call-off.']);
        } catch (ValidationException $exception) {
            return redirect()->route('portal.site-dashboard')->withErrors($exception->errors());
        }

        $payload = $this->confirmationPayload($request, $sourceRequest);
        $signature = $this->confirmationSignature($payload, $request->attributes->get('activeSite')->id, $request->user()->id);
        $request->session()->put(self::CONFIRMATION_SIGNATURE_SESSION_KEY, $signature);

        return view('portal.call-offs.resubmit.confirm', [
            'activeSite' => $request->attributes->get('activeSite'),
            'sourceRequest' => $sourceRequest,
            'requestedDate' => $request->date('requested_date'),
            'customerResponse' => $request->validated('customer_response'),
            'confirmationSignature' => $signature,
        ]);
    }

    public function store(
        ResubmitRejectedCallOffRequest $request,
        CallOffRequest $callOffRequest,
        ResubmitRejectedCallOffAction $resubmit,
    ): RedirectResponse {
        try {
            $sourceRequest = $this->authorisedSourceRequest($request, $callOffRequest);
            $this->ensureConfirmedPayload($request, $sourceRequest);

            $newRequest = $resubmit->handle(
                actor: $request->user(),
                sourceRequest: $sourceRequest,
                customerResponse: $request->validated('customer_response'),
                requestedDate: $request->validated('requested_date'),
            );
        } catch (AuthorizationException) {
            return redirect()->route('portal.site-dashboard')
                ->withErrors(['request' => 'You are no longer authorised to resubmit this call-off.']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('portal.call-offs.resubmit.create', $callOffRequest)
                ->withErrors($exception->errors())
                ->withInput();
        }

        $request->session()->forget(self::CONFIRMATION_SIGNATURE_SESSION_KEY);

        return redirect()
            ->route('portal.site-dashboard')
            ->with('status', 'Call-off resubmitted for '.$newRequest->projectedPlot->plot_reference.'.');
    }

    private function authorisedSourceRequest(Request $request, CallOffRequest $callOffRequest): CallOffRequest
    {
        $sourceRequest = CallOffRequest::query()
            ->where('uuid', $callOffRequest->uuid)
            ->whereHas('batch', fn ($query) => $query->where('site_id', $request->attributes->get('activeSite')->id))
            ->with([
                'projectedPlot:id,site_id,plot_reference,is_completed',
                'batch:id,uuid,site_id,submitted_by_user_id,service_identifier,requested_date,customer_response,submitted_at',
                'histories' => fn ($query) => $query
                    ->where('event_type', 'rejected')
                    ->orderByDesc('sequence')
                    ->select('id', 'call_off_request_id', 'event_type', 'sequence', 'customer_response'),
            ])
            ->firstOrFail();

        Gate::authorize('resubmit-call-off', $sourceRequest);

        return $sourceRequest;
    }

    /** @return array{source_request_uuid: string, requested_date: string, customer_response: string} */
    private function confirmationPayload(ResubmitRejectedCallOffRequest $request, CallOffRequest $sourceRequest): array
    {
        return [
            'source_request_uuid' => $sourceRequest->uuid,
            'requested_date' => (string) $request->validated('requested_date'),
            'customer_response' => (string) ($request->validated('customer_response') ?? ''),
        ];
    }

    /** @param array{source_request_uuid: string, requested_date: string, customer_response: string} $payload */
    private function confirmationSignature(array $payload, int $siteId, int $userId): string
    {
        return hash_hmac('sha256', json_encode([$siteId, $userId, $payload], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function ensureConfirmedPayload(ResubmitRejectedCallOffRequest $request, CallOffRequest $sourceRequest): void
    {
        $expectedSignature = $request->session()->get(self::CONFIRMATION_SIGNATURE_SESSION_KEY);
        $providedSignature = (string) $request->input('confirmation_signature', '');
        $actualSignature = $this->confirmationSignature(
            $this->confirmationPayload($request, $sourceRequest),
            $request->attributes->get('activeSite')->id,
            $request->user()->id,
        );

        if (! is_string($expectedSignature) || ! hash_equals($expectedSignature, $providedSignature) || ! hash_equals($expectedSignature, $actualSignature)) {
            throw ValidationException::withMessages(['request' => 'Review the resubmission details again before submitting.']);
        }
    }
}
