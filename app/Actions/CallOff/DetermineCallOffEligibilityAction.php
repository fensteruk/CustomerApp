<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffOperationType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DetermineCallOffEligibilityAction
{
    /**
     * @param  iterable<int, ProjectedPlot>  $projectedPlots
     */
    public function ensureCanSubmitBatch(User $user, Site $site, CallOffServiceType|string $serviceType, iterable $projectedPlots): void
    {
        $serviceType = $this->normaliseServiceType($serviceType);
        $plots = Collection::make($projectedPlots)->values();

        $this->ensureActiveSiteUser($user, $site);

        if ($plots->isEmpty()) {
            throw ValidationException::withMessages(['projected_plots' => 'Select at least one projected plot.']);
        }

        if ($plots->pluck('id')->unique()->count() !== $plots->count()) {
            throw ValidationException::withMessages(['projected_plots' => 'Each projected plot may only be selected once.']);
        }

        foreach ($plots as $plot) {
            $this->ensureProjectedPlotCanBeRequested($plot, $site, $serviceType);
        }
    }

    public function ensureCanApprove(User $user, CallOffRequest $request): void
    {
        $this->ensureOfficeStaffCanReview($user, $request);
        $this->ensureStatus($request, CallOffRequestStatus::Submitted, 'Only submitted requests may be approved.');
        $this->ensureNotTrashed($request);
    }

    public function ensureCanReject(User $user, CallOffRequest $request): void
    {
        $this->ensureOfficeStaffCanReview($user, $request);
        $this->ensureStatus($request, CallOffRequestStatus::Submitted, 'Only submitted requests may be rejected.');
        $this->ensureNotTrashed($request);
    }

    public function ensureCanWithdraw(User $user, CallOffRequest $request): void
    {
        $this->ensureSiteUserCanActOnRequest($user, $request);
        $this->ensureStatus($request, CallOffRequestStatus::Submitted, 'Only submitted requests may be withdrawn.');
        $this->ensureNotTrashed($request);
    }

    public function ensureCanTrash(User $user, CallOffRequest $request): void
    {
        $this->ensureSiteUserCanActOnRequest($user, $request);

        if (! in_array($request->status, [CallOffRequestStatus::Rejected, CallOffRequestStatus::Withdrawn], true)) {
            throw ValidationException::withMessages(['status' => 'Only rejected or withdrawn requests may be moved to Trash.']);
        }

        $this->ensureNotTrashed($request);
    }

    public function ensureCanRestore(User $user, CallOffRequest $request): void
    {
        $this->ensureSiteUserCanActOnRequest($user, $request);

        if ($request->trashed_at === null) {
            throw ValidationException::withMessages(['trash' => 'Only trashed requests may be restored.']);
        }

        if ($request->trash_expires_at === null || $request->trash_expires_at->isPast()) {
            throw ValidationException::withMessages(['trash' => 'This Trash item is no longer restorable.']);
        }
    }

    public function ensureCanUndo(User $user, CallOffBatchOperation $operation): void
    {
        $operation->loadMissing('batch', 'items.request.batch');

        if (! $user->isSiteRole() || ! $user->canAccessSite($operation->batch->site)) {
            throw new AuthorizationException;
        }

        if ((int) $operation->performed_by_user_id !== (int) $user->id) {
            throw new AuthorizationException;
        }

        if (! in_array($operation->operation_type, [
            CallOffOperationType::Withdrawal,
            CallOffOperationType::Trash,
            CallOffOperationType::Restore,
        ], true)) {
            throw ValidationException::withMessages(['operation' => 'This operation cannot be undone.']);
        }

        if ($operation->reversed_by_operation_id !== null) {
            throw ValidationException::withMessages(['operation' => 'This operation has already been undone.']);
        }

        if ($operation->undo_expires_at === null || $operation->undo_expires_at->isPast()) {
            throw ValidationException::withMessages(['operation' => 'The Undo window has expired.']);
        }
    }

    public function ensureCanResubmit(User $user, CallOffRequest $request): void
    {
        $request->loadMissing('batch', 'projectedPlot');
        $this->ensureSiteUserCanActOnRequest($user, $request);
        $this->ensureStatus($request, CallOffRequestStatus::Rejected, 'Only rejected requests may be resubmitted.');
        $this->ensureNotTrashed($request);
        $this->ensureProjectedPlotCanBeRequested($request->projectedPlot, $request->batch->site, $request->batch->service_identifier);
    }

    public function canViewProjectedPlot(User $user, ProjectedPlot $plot): bool
    {
        $plot->loadMissing('site');

        return $user->hasCompletePortalProfile()
            && $user->customer_organisation_id === $plot->site->customer_organisation_id
            && $user->canAccessSite($plot->site);
    }

    private function normaliseServiceType(CallOffServiceType|string $serviceType): CallOffServiceType
    {
        if ($serviceType instanceof CallOffServiceType) {
            return $serviceType;
        }

        return CallOffServiceType::tryFrom($serviceType)
            ?? throw ValidationException::withMessages(['service_identifier' => 'The selected service is not supported.']);
    }

    private function ensureActiveSiteUser(User $user, Site $site): void
    {
        if (! $user->hasCompletePortalProfile() || ! $user->isSiteRole() || ! $user->canAccessSite($site)) {
            throw new AuthorizationException;
        }
    }

    private function ensureOfficeStaffCanReview(User $user, CallOffRequest $request): void
    {
        $request->loadMissing('batch.site');

        if (! $user->hasCompletePortalProfile() || ! $user->isFensterOfficeStaff() || ! $user->canAccessSite($request->batch->site)) {
            throw new AuthorizationException;
        }
    }

    private function ensureSiteUserCanActOnRequest(User $user, CallOffRequest $request): void
    {
        $request->loadMissing('batch.site');
        $this->ensureActiveSiteUser($user, $request->batch->site);
    }

    private function ensureProjectedPlotCanBeRequested(ProjectedPlot $plot, Site $site, CallOffServiceType $serviceType): void
    {
        if ((int) $plot->site_id !== (int) $site->id) {
            throw ValidationException::withMessages(['projected_plots' => 'A selected projected plot does not belong to the selected site.']);
        }

        if ($plot->is_completed) {
            throw ValidationException::withMessages(['projected_plots' => 'Completed projected plots cannot receive new call-offs.']);
        }

        $conflictKey = UpdateConflictKeyAction::keyFor((int) $plot->id, $serviceType->value);

        if (CallOffRequest::query()->where('active_conflict_key', $conflictKey)->exists()) {
            throw ValidationException::withMessages(['projected_plots' => 'A selected projected plot already has an active request for this service.']);
        }
    }

    private function ensureStatus(CallOffRequest $request, CallOffRequestStatus $expectedStatus, string $message): void
    {
        if ($request->status !== $expectedStatus) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function ensureNotTrashed(CallOffRequest $request): void
    {
        if ($request->trashed_at !== null) {
            throw ValidationException::withMessages(['trash' => 'Trashed requests are not eligible for this action.']);
        }
    }
}
