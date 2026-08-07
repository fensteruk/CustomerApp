<?php

namespace App\Http\Controllers;

use App\Actions\CallOff\QuickUndoCallOffOperationAction;
use App\Actions\CallOff\RestoreCallOffRequestsAction;
use App\Actions\CallOff\TrashCallOffRequestsAction;
use App\Actions\CallOff\WithdrawCallOffRequestsAction;
use App\Http\Requests\CallOffLifecycleSelectionRequest;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CallOffLifecycleController extends Controller
{
    private const CONFIRMATION_SESSION_KEY = 'call_off_lifecycle_confirmation_signature';

    public function confirm(CallOffLifecycleSelectionRequest $request): View|RedirectResponse
    {
        $operation = $request->validated('operation');
        $activeSite = $request->attributes->get('activeSite');

        try {
            $requests = $this->selectedRequests($request, $activeSite->id);
            $this->ensureSelectionCanBeActioned($request, $operation, $requests);
        } catch (AuthorizationException) {
            return redirect()->route($this->returnRoute($operation))
                ->withErrors(['requests' => 'You are no longer authorised to change one or more selected call-offs.']);
        } catch (ValidationException $exception) {
            return redirect()->route($this->returnRoute($operation))
                ->withErrors($exception->errors());
        }

        $payload = $this->confirmationPayload($operation, $requests);
        $signature = $this->confirmationSignature($payload, $activeSite->id, $request->user()->id);
        $request->session()->put(self::CONFIRMATION_SESSION_KEY, $signature);

        return view('portal.call-offs.lifecycle-confirm', [
            'activeSite' => $activeSite,
            'operation' => $operation,
            'selectedRequests' => $requests,
            'confirmationSignature' => $signature,
        ]);
    }

    public function perform(
        Request $request,
        string $operation,
        WithdrawCallOffRequestsAction $withdraw,
        TrashCallOffRequestsAction $trash,
        RestoreCallOffRequestsAction $restore,
    ): RedirectResponse {
        abort_unless(in_array($operation, ['withdraw', 'trash', 'restore'], true), 404);

        $validated = validator($request->all(), (new CallOffLifecycleSelectionRequest)->rules())->validate();

        if ($validated['operation'] !== $operation) {
            return redirect()->route($this->returnRoute($operation))
                ->withErrors(['requests' => 'Review the selected call-offs again before continuing.']);
        }

        $activeSite = $request->attributes->get('activeSite');

        try {
            $requests = $this->selectedRequestsFromUuids($validated['requests'], $activeSite->id);
            $this->ensureConfirmedPayload($request, $operation, $requests, $activeSite->id);

            $completedOperation = match ($operation) {
                'withdraw' => $withdraw->handle($request->user(), $requests),
                'trash' => $trash->handle($request->user(), $requests),
                'restore' => $restore->handle($request->user(), $requests),
            };
        } catch (AuthorizationException) {
            return redirect()->route($this->returnRoute($operation))
                ->withErrors(['requests' => 'You are no longer authorised to change one or more selected call-offs.']);
        } catch (ValidationException $exception) {
            return redirect()->route($this->returnRoute($operation))
                ->withErrors($exception->errors());
        }

        $request->session()->forget(self::CONFIRMATION_SESSION_KEY);

        return redirect()->route($this->returnRoute($operation))
            ->with('status', $this->successMessage($operation, $requests->count()))
            ->with('quickUndo', [
                'operation_uuid' => $completedOperation->uuid,
                'undo_expires_at' => $completedOperation->undo_expires_at?->toIso8601String(),
            ]);
    }

    public function trash(Request $request): View
    {
        $activeSite = $request->attributes->get('activeSite');

        $requests = CallOffRequest::query()
            ->customerTrash()
            ->whereHas('batch', fn ($query) => $query->where('site_id', $activeSite->id))
            ->with([
                'projectedPlot:id,plot_reference',
                'batch:id,site_id,submitted_by_user_id,service_identifier,requested_date',
                'batch.submittedBy:id,name',
                'histories' => fn ($query) => $query
                    ->whereIn('event_type', ['approved', 'rejected'])
                    ->whereNotNull('customer_response')
                    ->orderByDesc('sequence')
                    ->select('id', 'call_off_request_id', 'event_type', 'sequence', 'customer_response'),
            ])
            ->orderByDesc('trashed_at')
            ->get();

        return view('portal.call-offs.trash', [
            'activeSite' => $activeSite,
            'trashedRequests' => $requests,
        ]);
    }

    public function undo(Request $request, string $operationUuid, QuickUndoCallOffOperationAction $quickUndo): RedirectResponse
    {
        abort_unless(str($operationUuid)->isUuid(), 404);

        $activeSite = $request->attributes->get('activeSite');
        $operation = CallOffBatchOperation::query()
            ->where('uuid', $operationUuid)
            ->whereHas('batch', fn ($query) => $query->where('site_id', $activeSite->id))
            ->firstOrFail();

        try {
            $undo = $quickUndo->handle($request->user(), $operation);
        } catch (AuthorizationException) {
            return redirect()->route('portal.site-dashboard')
                ->withErrors(['operation' => 'You are no longer authorised to undo this action.']);
        } catch (ValidationException $exception) {
            return redirect()->route('portal.site-dashboard')
                ->withErrors($exception->errors());
        }

        return redirect()->route('portal.site-dashboard')
            ->with('status', 'The previous call-off action was undone for '.$undo->items->count().' selected '.str('request')->plural($undo->items->count()).'.');
    }

    /**
     * @return Collection<int, CallOffRequest>
     */
    private function selectedRequests(CallOffLifecycleSelectionRequest $request, int $siteId): Collection
    {
        return $this->selectedRequestsFromUuids($request->validated('requests'), $siteId);
    }

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<int, CallOffRequest>
     */
    private function selectedRequestsFromUuids(array $uuids, int $siteId): Collection
    {
        $requests = CallOffRequest::query()
            ->whereIn('uuid', $uuids)
            ->whereHas('batch', fn ($query) => $query->where('site_id', $siteId))
            ->with(['projectedPlot:id,plot_reference', 'batch:id,site_id,service_identifier,requested_date'])
            ->get()
            ->keyBy('uuid');

        if ($requests->count() !== count($uuids)) {
            throw ValidationException::withMessages(['requests' => 'One or more selected call-offs are no longer available for this site.']);
        }

        return collect($uuids)->map(fn (string $uuid): CallOffRequest => $requests->get($uuid))->values();
    }

    /**
     * @param  Collection<int, CallOffRequest>  $requests
     */
    private function ensureSelectionCanBeActioned(Request $request, string $operation, Collection $requests): void
    {
        if ($requests->pluck('call_off_batch_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['requests' => 'Select call-offs from one submission batch at a time.']);
        }

        $ability = match ($operation) {
            'withdraw' => 'withdraw-call-off',
            'trash' => 'trash-call-off',
            'restore' => 'restore-call-off',
        };

        foreach ($requests as $callOffRequest) {
            Gate::authorize($ability, $callOffRequest);
        }
    }

    /**
     * @param  Collection<int, CallOffRequest>  $requests
     * @return array{operation: string, requests: array<int, string>}
     */
    private function confirmationPayload(string $operation, Collection $requests): array
    {
        return [
            'operation' => $operation,
            'requests' => $requests->pluck('uuid')->values()->all(),
        ];
    }

    /**
     * @param  array{operation: string, requests: array<int, string>}  $payload
     */
    private function confirmationSignature(array $payload, int $siteId, int $userId): string
    {
        return hash_hmac('sha256', json_encode([$siteId, $userId, $payload], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /**
     * @param  Collection<int, CallOffRequest>  $requests
     */
    private function ensureConfirmedPayload(Request $request, string $operation, Collection $requests, int $siteId): void
    {
        $expected = $request->session()->get(self::CONFIRMATION_SESSION_KEY);
        $provided = (string) $request->input('confirmation_signature', '');
        $actual = $this->confirmationSignature($this->confirmationPayload($operation, $requests), $siteId, $request->user()->id);

        if (! is_string($expected) || ! hash_equals($expected, $provided) || ! hash_equals($expected, $actual)) {
            throw ValidationException::withMessages(['requests' => 'Review the selected call-offs again before continuing.']);
        }
    }

    private function returnRoute(string $operation): string
    {
        return $operation === 'restore' ? 'portal.call-offs.trash' : 'portal.site-dashboard';
    }

    private function successMessage(string $operation, int $count): string
    {
        $subject = $count.' selected '.str('call-off')->plural($count);

        return match ($operation) {
            'withdraw' => ucfirst($subject).' '.str('was')->plural($count).' withdrawn.',
            'trash' => ucfirst($subject).' moved to Trash.',
            'restore' => ucfirst($subject).' restored from Trash.',
        };
    }
}
