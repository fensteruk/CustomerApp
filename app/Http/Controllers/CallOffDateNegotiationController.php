<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RejectAlternativeCallOffDateAction;
use App\Http\Requests\AcceptAlternativeCallOffDateRequest;
use App\Http\Requests\AgreeRequestedCallOffDateRequest;
use App\Http\Requests\ProposeAlternativeCallOffDateRequest;
use App\Http\Requests\RespondToAlternativeCallOffDateRequest;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CallOffDateNegotiationController extends Controller
{
    public function agree(
        AgreeRequestedCallOffDateRequest $request,
        CallOffRequest $callOffRequest,
        AgreeRequestedCallOffDateAction $agreeRequestedDate,
    ): RedirectResponse {
        try {
            $agreed = $agreeRequestedDate->handle(
                $request->user(),
                $callOffRequest,
                $request->boolean('early_date_acknowledgement'),
                $request->validated('negotiation_uuid'),
            );
        } catch (AuthorizationException) {
            return $this->notAuthorisedForOfficeReview();
        } catch (ValidationException $exception) {
            return $this->reviewValidationFailure($callOffRequest, $exception);
        }

        return redirect()->route('portal.review-requests.show', $agreed)
            ->with('status', 'The requested date has been agreed.');
    }

    public function propose(
        ProposeAlternativeCallOffDateRequest $request,
        CallOffRequest $callOffRequest,
        ProposeAlternativeCallOffDateAction $proposeAlternativeDate,
    ): RedirectResponse {
        try {
            $proposal = $proposeAlternativeDate->handle(
                $request->user(),
                $callOffRequest,
                $request->validated('proposed_date'),
                $request->validated('customer_response'),
                $request->validated('internal_reason'),
                $request->validated('negotiation_uuid'),
                $request->boolean('early_date_acknowledgement'),
            );
        } catch (AuthorizationException) {
            return $this->notAuthorisedForOfficeReview();
        } catch (ValidationException $exception) {
            return $this->reviewValidationFailure($callOffRequest, $exception);
        }

        return redirect()->route('portal.review-requests.show', $callOffRequest)
            ->with('status', 'An alternative date has been proposed for '.$proposal->proposed_date->toDateString().'.');
    }

    public function accept(
        AcceptAlternativeCallOffDateRequest $request,
        CallOffRequest $callOffRequest,
        CallOffDateProposal $callOffDateProposal,
        AcceptAlternativeCallOffDateAction $acceptAlternativeDate,
    ): RedirectResponse {
        $this->ensureActiveRequestSite($request, $callOffRequest);
        try {
            $acceptAlternativeDate->handle($request->user(), $callOffRequest, $callOffDateProposal);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $exception) {
            return redirect()->route('portal.call-offs.show', $callOffRequest)
                ->withErrors(['status' => 'This request changed while you were viewing it. Refresh to see the latest status.']);
        }

        return redirect()->route('portal.call-offs.show', $callOffRequest)
            ->with('status', 'The alternative date has been accepted. The proposed date is now the Date Agreed.');
    }

    public function reject(
        RespondToAlternativeCallOffDateRequest $request,
        CallOffRequest $callOffRequest,
        CallOffDateProposal $callOffDateProposal,
        RejectAlternativeCallOffDateAction $rejectAlternativeDate,
    ): RedirectResponse {
        $this->ensureActiveRequestSite($request, $callOffRequest);
        try {
            $rejectAlternativeDate->handle(
                $request->user(),
                $callOffRequest,
                $callOffDateProposal,
                $request->validated('customer_response'),
            );
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $exception) {
            return redirect()->route('portal.call-offs.show', $callOffRequest)
                ->withErrors(['status' => 'This request changed while you were viewing it. Refresh to see the latest status.'])
                ->withInput();
        }

        return redirect()->route('portal.call-offs.show', $callOffRequest)
            ->with('status', 'The alternative date has been rejected and Fenster has been notified.');
    }

    private function ensureActiveRequestSite(Request $request, CallOffRequest $callOffRequest): void
    {
        abort_unless($request->user()->canAccessSite($callOffRequest->batch->site), 403);
        abort_unless((int) $request->attributes->get('activeSite')?->id === (int) $callOffRequest->batch->site_id, 404);
    }

    private function notAuthorisedForOfficeReview(): RedirectResponse
    {
        return redirect()->route('portal.review-requests')
            ->withErrors(['request' => 'You are no longer authorised to decide this request.']);
    }

    private function reviewValidationFailure(CallOffRequest $callOffRequest, ValidationException $exception): RedirectResponse
    {
        return redirect()->route('portal.review-requests.show', $callOffRequest)
            ->withErrors($exception->errors())
            ->withInput();
    }
}
