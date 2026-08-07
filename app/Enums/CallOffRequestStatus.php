<?php

namespace App\Enums;

enum CallOffRequestStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'slate',
        };
    }

    public function isConflictActive(): bool
    {
        return in_array($this, [self::Submitted, self::Approved], true);
    }
}
