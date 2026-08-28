<?php

namespace App\Enums;

enum WorkbookColumnRole: string
{
    case CallNumber = 'CALL_NUMBER';
    case SiteName = 'SITE_NAME';
    case SiteExternalId = 'SITE_EXTERNAL_ID';
    case PlotReference = 'PLOT_REFERENCE';
    case CallType = 'CALL_TYPE';
    case CompletionFlag = 'COMPLETION_FLAG';
    case CompletedDate = 'COMPLETED_DATE';
    case OperationalTargetDate = 'OPERATIONAL_TARGET_DATE';
    case ProductQuantity = 'PRODUCT_QUANTITY';
    case CommercialValue = 'COMMERCIAL_VALUE';
    case Ignore = 'IGNORE';
    case Unknown = 'UNKNOWN';

    public function isCritical(): bool
    {
        return in_array($this, [self::CallNumber, self::SiteName, self::PlotReference, self::CallType], true);
    }
}
