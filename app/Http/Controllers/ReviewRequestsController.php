<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\ApproveCallOffRequestAction;
use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Http\Requests\ApproveCallOffDecisionRequest;
use App\Http\Requests\RejectCallOffDecisionRequest;
use App\Models\CallOffRequest;
use App\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewRequestsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user?->isFensterOfficeStaff(), 403);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(CallOffRequestStatus::class)],
            'site' => ['nullable', 'integer'],
            'service' => ['nullable', 'string', Rule::enum(CallOffServiceType::class)],
        ]);

        $assignedSites = Site::query()
            ->orderBy('name')
            ->get(['sites.id', 'sites.name']);

        $status = $validated['status'] ?? CallOffRequestStatus::Submitted->value;

        $requests = CallOffRequest::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(isset($validated['site']), function ($query) use ($validated): void {
                $query->whereHas('batch', fn ($batchQuery) => $batchQuery
                    ->where('site_id', (int) $validated['site']));
            })
            ->when(isset($validated['service']), fn ($query) => $query
                ->whereHas('batch', fn ($batchQuery) => $batchQuery->where('service_identifier', $validated['service'])))
            ->with([
                'projectedPlot:id,plot_reference',
                'batch:id,uuid,site_id,submitted_by_user_id,service_identifier,requested_date,customer_response,submitted_at',
                'batch.site:id,customer_organisation_id,name',
                'batch.submittedBy:id,name',
            ])
            ->orderByRaw(
                '(select submitted_at from call_off_batches where call_off_batches.id = call_off_requests.call_off_batch_id) desc'
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('portal.review-requests.index', [
            'assignedSites' => $assignedSites,
            'requests' => $requests,
            'serviceTypes' => CallOffServiceType::cases(),
            'statuses' => CallOffRequestStatus::cases(),
            'filters' => [
                'status' => $status,
                'site' => $validated['site'] ?? '',
                'service' => $validated['service'] ?? '',
            ],
        ]);
    }

    public function show(Request $request, CallOffRequest $callOffRequest): View
    {
        $user = $request->user();

        abort_unless($user?->isFensterOfficeStaff(), 403);

        $callOffRequest = $this->authorisedRequest($user, $callOffRequest);

        return view('portal.review-requests.show', [
            'callOffRequest' => $callOffRequest,
        ]);
    }

    public function approve(
        ApproveCallOffDecisionRequest $request,
        CallOffRequest $callOffRequest,
        ApproveCallOffRequestAction $approveCallOffRequest,
    ): RedirectResponse {
        abort_unless($request->user()?->isFensterOfficeStaff(), 403);

        try {
            $approved = $approveCallOffRequest->handle(
                actor: $request->user(),
                request: $callOffRequest,
                customerResponse: $request->validated('customer_response'),
                internalReason: $request->validated('internal_reason'),
            );
        } catch (AuthorizationException) {
            return redirect()
                ->route('portal.review-requests')
                ->withErrors(['request' => 'You are no longer authorised to decide this request.']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('portal.review-requests.show', $callOffRequest)
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('portal.review-requests')
            ->with('status', 'Call-off for '.$approved->projectedPlot->plot_reference.' was approved.');
    }

    public function reject(
        RejectCallOffDecisionRequest $request,
        CallOffRequest $callOffRequest,
        RejectCallOffRequestAction $rejectCallOffRequest,
    ): RedirectResponse {
        abort_unless($request->user()?->isFensterOfficeStaff(), 403);

        try {
            $rejected = $rejectCallOffRequest->handle(
                actor: $request->user(),
                request: $callOffRequest,
                customerResponse: $request->validated('customer_response'),
                internalReason: $request->validated('internal_reason'),
            );
        } catch (AuthorizationException) {
            return redirect()
                ->route('portal.review-requests')
                ->withErrors(['request' => 'You are no longer authorised to decide this request.']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('portal.review-requests.show', $callOffRequest)
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('portal.review-requests')
            ->with('status', 'Call-off for '.$rejected->projectedPlot->plot_reference.' was rejected.');
    }

    private function authorisedRequest($user, CallOffRequest $callOffRequest): CallOffRequest
    {
        $callOffRequest->loadMissing('batch.site');

        abort_unless($user->hasCompletePortalProfile() && $user->isFensterOfficeStaff(), 404);

        return $callOffRequest->load([
            'projectedPlot:id,site_id,plot_reference',
            'batch:id,uuid,site_id,submitted_by_user_id,service_identifier,requested_date,customer_response,submitted_at',
            'batch.site:id,customer_organisation_id,name,location',
            'batch.site.customerOrganisation:id,name',
            'batch.submittedBy:id,name',
            'histories' => fn ($query) => $query
                ->with('performedBy:id,name')
                ->orderBy('sequence'),
        ]);
    }
}
