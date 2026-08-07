<?php

namespace App\Enums;

enum PortalNotificationType: string
{
    case CallOffSubmitted = 'call_off_submitted';
    case CallOffApproved = 'call_off_approved';
    case CallOffRejected = 'call_off_rejected';

    public function label(): string
    {
        return match ($this) {
            self::CallOffSubmitted => 'Call-off submitted',
            self::CallOffApproved => 'Call-off approved',
            self::CallOffRejected => 'Call-off rejected',
        };
    }
}
