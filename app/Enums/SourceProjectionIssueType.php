<?php

namespace App\Enums;

enum SourceProjectionIssueType: string
{
    case UnknownCallType = 'unknown_call_type';
    case AssociationChanged = 'association_changed';
    case CompletionDateMissing = 'completion_date_missing';
    case DuplicateCallNumber = 'duplicate_call_number';
    case MissingSourceRecord = 'missing_source_record';
    case UnsafeCompletionReversal = 'unsafe_completion_reversal';
    case InvalidSourceRecord = 'invalid_source_record';
}
