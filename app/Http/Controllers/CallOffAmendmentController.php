<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\DetermineCallOffEligibilityAction;
use App\Actions\CallOff\RequestCallOffAmendmentAction;
use App\Models\CallOffRequest;
use App\Services\CallOffAmendmentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CallOffAmendmentController extends Controller
{
    public function __construct(
        private readonly CallOffAmendmentRules $rules,
        private readonly DetermineCallOffEligibilityAction $eligibility,
    ) {}

    public function create(Request $http, CallOffRequest $callOffRequest)
    {
        $this->authorise($http, $callOffRequest);
        $this->eligibility->ensureCanRequestAmendment($http->user(), $callOffRequest);
        $agreedDate = $callOffRequest->agreed_date ?? $callOffRequest->requested_date ?? $callOffRequest->batch->requested_date;

        return view('portal.call-offs.amendments.create', [
            'callOffRequest' => $callOffRequest,
            'agreedDate' => $agreedDate,
            'isUrgent' => $this->rules->isUrgent($agreedDate),
            'reasons' => $this->rules->reasons(),
            'explanationRequiredReason' => CallOffAmendmentRules::EXPLANATION_REQUIRED_REASON,
        ]);
    }

    public function review(Request $http, CallOffRequest $callOffRequest)
    {
        $this->authorise($http, $callOffRequest);
        $this->eligibility->ensureCanRequestAmendment($http->user(), $callOffRequest);
        $data = $this->rules->validate($http->all());
        $this->rules->validateDate($data['requested_date']);
        $agreedDate = $callOffRequest->agreed_date ?? $callOffRequest->requested_date ?? $callOffRequest->batch->requested_date;
        $token = Str::random(64);
        $http->session()->put('amendment_review.'.$callOffRequest->uuid, [
            'token' => hash('sha256', $token), 'user_id' => $http->user()->id,
            'site_id' => $http->attributes->get('activeSite')->id,
            'expires_at' => now()->addMinutes(config('call_off_amendments.confirmation_minutes', 15))->timestamp,
            'revision' => $this->rules->revision($callOffRequest), 'data' => $data,
        ]);

        return view('portal.call-offs.amendments.review', [
            'callOffRequest' => $callOffRequest, 'agreedDate' => $agreedDate,
            'data' => $data, 'reasonLabel' => $this->rules->reasons()[$data['reason_code']],
            'isUrgent' => $this->rules->isUrgent($agreedDate), 'token' => $token,
        ]);
    }

    public function store(Request $http, CallOffRequest $callOffRequest, RequestCallOffAmendmentAction $action)
    {
        $this->authorise($http, $callOffRequest);
        $http->validate(['confirmation_token' => ['required', 'string', 'size:64']]);
        $review = $http->session()->pull('amendment_review.'.$callOffRequest->uuid);
        if (! is_array($review) || ! hash_equals($review['token'], hash('sha256', $http->string('confirmation_token')->toString()))
            || $review['user_id'] !== $http->user()->id || $review['site_id'] !== $http->attributes->get('activeSite')->id
            || $review['expires_at'] <= now()->timestamp) {
            throw ValidationException::withMessages(['request' => 'Review this date change again before submitting.']);
        }
        try {
            $action->handle($http->user(), $http->attributes->get('activeSite'), $callOffRequest, $review['data'], $review['revision']);
        } catch (ValidationException $exception) {
            return redirect()->route('portal.call-offs.show', $callOffRequest)->withErrors($exception->errors());
        }

        return redirect()->route('portal.call-offs.show', $callOffRequest)->with('status', 'Your date change has been requested. The previous agreed date is now On Hold.');
    }

    private function authorise(Request $http, CallOffRequest $request): void
    {
        $request->loadMissing('batch.site', 'projectedPlot', 'projectedPlotService');
        abort_unless($http->user()?->isSiteRole(), 403);
        abort_unless((int) $http->attributes->get('activeSite')?->id === (int) $request->batch->site_id
            && $http->user()->canAccessSite($request->batch->site), 404);
    }
}
