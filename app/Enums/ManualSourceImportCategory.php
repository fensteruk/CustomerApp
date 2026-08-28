<?php

namespace App\Enums;

enum ManualSourceImportCategory: string
{
    case New = 'NEW';
    case Unchanged = 'UNCHANGED';
    case Updated = 'UPDATED';
    case Completed = 'COMPLETED';
    case CompletionReversed = 'COMPLETION_REVERSED';
    case MissingFromSource = 'MISSING_FROM_SOURCE';
    case SiteMappingRequired = 'SITE_MAPPING_REQUIRED';
    case UnknownCallType = 'UNKNOWN_CALL_TYPE';
    case Invalid = 'INVALID';
    case ReconciliationRequired = 'RECONCILIATION_REQUIRED';
}
