<?php

namespace App\Services\Reconciliation;

use App\Enums\CallOffServiceType;

/** Only management-approved per-service date contracts may be added here. */
final class SourceConfirmationContract
{
    public const VERSION = 'source-confirmation-unconfigured.v1';

    public function forService(CallOffServiceType $service): array
    {
        return ['service' => $service->value, 'supported' => false, 'call_types' => [], 'date_field' => null,
            'status' => 'deferred', 'explanation' => 'An authoritative RedZebra confirmation date has not yet been approved for '.$service->label().'.'];
    }
}
