<?php

namespace App\Enums;

enum CallOffRequestStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case AwaitingFenster = 'awaiting_fenster';
    case AwaitingSiteUser = 'awaiting_site_user';
    case DateAgreed = 'date_agreed';
    case AmendmentOnHold = 'amendment_on_hold';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::AwaitingFenster => 'Awaiting Fenster',
            self::AwaitingSiteUser => 'Awaiting Site User',
            self::DateAgreed => 'Date Agreed',
            self::AmendmentOnHold => 'Amendment On Hold',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'slate',
            self::AwaitingFenster, self::AwaitingSiteUser, self::AmendmentOnHold => 'amber',
            self::DateAgreed => 'green',
            self::Completed => 'blue',
        };
    }

    public function isConflictActive(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::Approved,
            self::AwaitingFenster,
            self::AwaitingSiteUser,
            self::DateAgreed,
            self::AmendmentOnHold,
        ], true);
    }

    public function isLegacy(): bool
    {
        return in_array($this, [self::Submitted, self::Approved, self::Rejected, self::Withdrawn], true);
    }
}
