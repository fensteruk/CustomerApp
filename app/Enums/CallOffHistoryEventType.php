<?php

namespace App\Enums;

enum CallOffHistoryEventType: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Trashed = 'trashed';
    case Restored = 'restored';
    case UndoApplied = 'undo_applied';
    case Resubmitted = 'resubmitted';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::Trashed => 'Moved to Trash',
            self::Restored => 'Restored',
            self::UndoApplied => 'Undo applied',
            self::Resubmitted => 'Resubmitted',
        };
    }
}
