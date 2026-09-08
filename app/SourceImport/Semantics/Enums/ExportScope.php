<?php

namespace App\SourceImport\Semantics\Enums;

enum ExportScope: string
{
    case PartialFilteredExport = 'PARTIAL_FILTERED_EXPORT';
    case SiteCompleteSnapshot = 'SITE_COMPLETE_SNAPSHOT';
    case GlobalCompleteSnapshot = 'GLOBAL_COMPLETE_SNAPSHOT';
}
