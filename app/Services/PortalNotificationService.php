<?php

namespace App\Services;

use App\Enums\CallOffRequestStatus;
use App\Enums\PortalNotificationType;
use App\Models\CallOffRequest;
use App\Models\PortalNotification;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortalNotificationService
{
    public function createForRequest(CallOffRequest $request, PortalNotificationType $type): void
    {
        $request->loadMissing([
            'batch.site.customerOrganisation',
            'batch.submittedBy.portalRole',
            'projectedPlot',
        ]);

        if ($request->batch->submittedBy->is_preview_user) {
            return;
        }

        $expectedStatus = match ($type) {
            PortalNotificationType::CallOffSubmitted => CallOffRequestStatus::Submitted,
            PortalNotificationType::CallOffApproved => CallOffRequestStatus::Approved,
            PortalNotificationType::CallOffRejected => CallOffRequestStatus::Rejected,
        };

        if ($request->status !== $expectedStatus) {
            return;
        }

        $site = $request->batch->site;
        $recipients = $this->recipients($request, $type, $site);
        $customerResponse = $type === PortalNotificationType::CallOffSubmitted
            ? $request->batch->customer_response
            : $request->histories()
                ->where('event_type', $type === PortalNotificationType::CallOffApproved ? 'approved' : 'rejected')
                ->latest('sequence')
                ->value('customer_response');

        DB::transaction(function () use ($recipients, $request, $site, $type, $customerResponse): void {
            foreach ($recipients as $recipient) {
                $this->createForRecipient($recipient, $request, $site, $type, $customerResponse);
            }
        });
    }

    /** @return Collection<int, User> */
    private function recipients(CallOffRequest $request, PortalNotificationType $type, Site $site): Collection
    {
        $submitter = $request->batch->submittedBy;

        if ($type !== PortalNotificationType::CallOffSubmitted) {
            return $this->activeAuthorisedSubmitter($submitter, $site);
        }

        $officeStaff = User::query()
            ->where('is_active', true)
            ->where('is_preview_user', false)
            ->where('customer_organisation_id', $site->customer_organisation_id)
            ->whereHas('portalRole', fn ($query) => $query->where('identifier', 'fenster_office_staff'))
            ->whereHas('assignedSites', fn ($query) => $query->whereKey($site->id))
            ->get();

        return $this->activeAuthorisedSubmitter($submitter, $site)
            ->merge($officeStaff)
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, User> */
    private function activeAuthorisedSubmitter(User $submitter, Site $site): Collection
    {
        if (! $submitter->hasCompletePortalProfile() || ! $submitter->isSiteRole() || ! $submitter->canAccessSite($site)) {
            return collect();
        }

        return collect([$submitter]);
    }

    private function createForRecipient(
        User $recipient,
        CallOffRequest $request,
        Site $site,
        PortalNotificationType $type,
        ?string $customerResponse,
    ): void {
        $eventKey = $type->value.':'.$request->uuid;
        $routeName = $recipient->isFensterOfficeStaff()
            ? 'portal.review-requests.show'
            : 'portal.site-dashboard';
        $routeParameters = $recipient->isFensterOfficeStaff()
            ? ['callOffRequest' => $request->uuid]
            : [];

        try {
            PortalNotification::query()->firstOrCreate(
                [
                    'notifiable_user_id' => $recipient->id,
                    'event_key' => $eventKey,
                ],
                [
                    'type' => $type,
                    'request_uuid' => $request->uuid,
                    'batch_uuid' => $request->batch->uuid,
                    'site_uuid' => null,
                    'site_name' => $site->name,
                    'plot_reference' => $request->projectedPlot->plot_reference,
                    'service_identifier' => $request->batch->service_identifier,
                    'requested_date' => $request->batch->requested_date,
                    'current_status' => $request->status,
                    'customer_response' => $customerResponse,
                    'route_name' => $routeName,
                    'route_parameters' => $routeParameters,
                ],
            );
        } catch (QueryException $exception) {
            if (! $this->isDuplicateEvent($recipient, $eventKey)) {
                throw $exception;
            }
        }
    }

    private function isDuplicateEvent(User $recipient, string $eventKey): bool
    {
        return PortalNotification::query()
            ->where('notifiable_user_id', $recipient->id)
            ->where('event_key', $eventKey)
            ->exists();
    }
}
