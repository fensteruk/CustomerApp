<?php

namespace App\Listeners;

use App\Enums\PortalNotificationType;
use App\Events\CallOffAlternativeAccepted;
use App\Events\CallOffAlternativeProposed;
use App\Events\CallOffAlternativeRejected;
use App\Events\CallOffApproved;
use App\Events\CallOffDateAgreed;
use App\Events\CallOffRejected;
use App\Events\CallOffSubmitted;
use App\Models\CallOffRequest;
use App\Services\PortalNotificationService;
use Illuminate\Support\Facades\Log;

class CallOffNotificationListener
{
    public function __construct(private readonly PortalNotificationService $notifications) {}

    public function submitted(CallOffSubmitted $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffSubmitted);
    }

    public function approved(CallOffApproved $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffApproved);
    }

    public function rejected(CallOffRejected $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffRejected);
    }

    public function dateAgreed(CallOffDateAgreed $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffDateAgreed);
    }

    public function alternativeProposed(CallOffAlternativeProposed $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffAlternativeProposed, $event->proposalUuid);
    }

    public function alternativeAccepted(CallOffAlternativeAccepted $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffAlternativeAccepted, $event->proposalUuid);
    }

    public function alternativeRejected(CallOffAlternativeRejected $event): void
    {
        $this->create($event->callOffRequestId, PortalNotificationType::CallOffAlternativeRejected, $event->proposalUuid);
    }

    private function create(int $requestId, PortalNotificationType $type, ?string $eventReference = null): void
    {
        try {
            $request = CallOffRequest::query()->find($requestId);

            if ($request !== null) {
                $this->notifications->createForRequest($request, $type, $eventReference);
            }
        } catch (\Throwable $exception) {
            Log::warning('Portal notification creation failed.', [
                'notification_type' => $type->value,
                'call_off_request_id' => $requestId,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
        }
    }
}
