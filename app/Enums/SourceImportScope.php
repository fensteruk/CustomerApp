<?php

namespace App\Enums;

enum SourceImportScope: string
{
    case PartialFilteredExport = 'PARTIAL_FILTERED_EXPORT';
    case SiteCompleteSnapshot = 'SITE_COMPLETE_SNAPSHOT';
    case GlobalCompleteSnapshot = 'GLOBAL_COMPLETE_SNAPSHOT';

    public function label(): string
    {
        return match ($this) {
            self::PartialFilteredExport => 'This spreadsheet is a filtered/partial export.',
            self::SiteCompleteSnapshot => 'This spreadsheet is complete for the explicitly selected site(s).',
            self::GlobalCompleteSnapshot => 'This spreadsheet is a complete global export.',
        };
    }
}
