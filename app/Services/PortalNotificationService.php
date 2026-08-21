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
    public function createForRequest(CallOffRequest $request, PortalNotificationType $type, ?string $eventReference = null): void
    {
        $request->loadMissing([
            'batch.site.customerOrganisation',
            'batch.submittedBy.portalRole',
            'projectedPlot',
        ]);

        if ($request->batch->submittedBy->is_preview_user) {
            return;
        }

        $expectedStatuses = match ($type) {
            PortalNotificationType::CallOffSubmitted => [CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster],
            PortalNotificationType::CallOffApproved => [CallOffRequestStatus::Approved],
            PortalNotificationType::CallOffRejected => [CallOffRequestStatus::Rejected],
            PortalNotificationType::CallOffDateAgreed, PortalNotificationType::CallOffAlternativeAccepted => [CallOffRequestStatus::DateAgreed],
            PortalNotificationType::CallOffAlternativeProposed => [CallOffRequestStatus::AwaitingSiteUser],
            PortalNotificationType::CallOffAlternativeRejected => [CallOffRequestStatus::AwaitingFenster],
        };

        if (! in_array($request->status, $expectedStatuses, true)) {
            return;
        }

        $site = $request->batch->site;
        $recipients = $this->recipients($request, $type, $site);
        $customerResponse = match ($type) {
            PortalNotificationType::CallOffSubmitted => $request->batch->customer_response,
            PortalNotificationType::CallOffApproved => $this->latestCustomerResponse($request, 'approved'),
            PortalNotificationType::CallOffRejected => $this->latestCustomerResponse($request, 'rejected'),
            PortalNotificationType::CallOffAlternativeProposed => $this->latestCustomerResponse($request, 'alternative_date_proposed'),
            PortalNotificationType::CallOffAlternativeRejected => $this->latestCustomerResponse($request, 'alternative_date_rejected'),
            PortalNotificationType::CallOffDateAgreed, PortalNotificationType::CallOffAlternativeAccepted => null,
        };

        DB::transaction(function () use ($recipients, $request, $site, $type, $customerResponse, $eventReference): void {
            foreach ($recipients as $recipient) {
                $this->createForRecipient($recipient, $request, $site, $type, $customerResponse, $eventReference);
            }
        });
    }

    /** @return Collection<int, User> */
    private function recipients(CallOffRequest $request, PortalNotificationType $type, Site $site): Collection
    {
        $submitter = $request->batch->submittedBy;

        if (in_array($type, [PortalNotificationType::CallOffAlternativeAccepted, PortalNotificationType::CallOffAlternativeRejected], true)) {
            return $this->activeOfficeStaff();
        }

        if ($type !== PortalNotificationType::CallOffSubmitted) {
            return $this->activeAuthorisedSubmitter($submitter, $site);
        }

        return $this->activeAuthorisedSubmitter($submitter, $site)
            ->merge($this->activeOfficeStaff())
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, User> */
    private function activeOfficeStaff(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('is_preview_user', false)
            ->whereHas('portalRole', fn ($query) => $query->where('identifier', 'fenster_office_staff'))
            ->get();
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
        ?string $eventReference,
    ): void {
        $eventKey = $type->value.':'.($eventReference ?? $request->uuid);
        $routeName = $recipient->isFensterOfficeStaff()
            ? 'portal.review-requests.show'
            : 'portal.call-offs.show';
        $routeParameters = $recipient->isFensterOfficeStaff()
            ? ['callOffRequest' => $request->uuid]
            : ['callOffRequest' => $request->uuid];

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
                    'service_identifier' => $request->effectiveServiceIdentifier(),
                    'requested_date' => $request->requested_date ?? $request->batch->requested_date,
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

    private function latestCustomerResponse(CallOffRequest $request, string $eventType): ?string
    {
        return $request->histories()
            ->where('event_type', $eventType)
            ->latest('sequence')
            ->value('customer_response');
    }
}
