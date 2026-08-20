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
    case DateRequested = 'date_requested';
    case AlternativeDateProposed = 'alternative_date_proposed';
    case AlternativeDateAccepted = 'alternative_date_accepted';
    case AlternativeDateRejected = 'alternative_date_rejected';
    case DateAgreed = 'date_agreed';
    case AmendmentRequested = 'amendment_requested';
    case AmendmentOnHold = 'amendment_on_hold';
    case PriorDateReinstated = 'prior_date_reinstated';
    case SourceCompletionRecorded = 'source_completion_recorded';
    case SourceCompletionReversed = 'source_completion_reversed';
    case EarlierDateExceptionAcknowledged = 'earlier_date_exception_acknowledged';

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
            self::DateRequested => 'Date requested',
            self::AlternativeDateProposed => 'Alternative date proposed',
            self::AlternativeDateAccepted => 'Alternative date accepted',
            self::AlternativeDateRejected => 'Alternative date rejected',
            self::DateAgreed => 'Date agreed',
            self::AmendmentRequested => 'Amendment requested',
            self::AmendmentOnHold => 'Amendment on hold',
            self::PriorDateReinstated => 'Prior date reinstated',
            self::SourceCompletionRecorded => 'Source completion recorded',
            self::SourceCompletionReversed => 'Source completion reversed',
            self::EarlierDateExceptionAcknowledged => 'Earlier-date exception acknowledged',
        };
    }
}
