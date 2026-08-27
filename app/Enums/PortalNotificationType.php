<?php

namespace App\Enums;

enum PortalNotificationType: string
{
    case CallOffSubmitted = 'call_off_submitted';
    case CallOffApproved = 'call_off_approved';
    case CallOffRejected = 'call_off_rejected';
    case CallOffDateAgreed = 'call_off_date_agreed';
    case CallOffAlternativeProposed = 'call_off_alternative_proposed';
    case CallOffAlternativeAccepted = 'call_off_alternative_accepted';
    case CallOffAlternativeRejected = 'call_off_alternative_rejected';

    public function label(): string
    {
        return match ($this) {
            self::CallOffSubmitted => 'Call-off submitted',
            self::CallOffApproved => 'Call-off approved',
            self::CallOffRejected => 'Call-off rejected',
            self::CallOffDateAgreed => 'Date agreed',
            self::CallOffAlternativeProposed => 'Alternative date proposed',
            self::CallOffAlternativeAccepted => 'Alternative date accepted',
            self::CallOffAlternativeRejected => 'Alternative date rejected',
        };
    }
}
