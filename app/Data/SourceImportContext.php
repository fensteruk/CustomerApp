<?php

namespace App\Data;

use App\Enums\SourceImportScope;

readonly class SourceImportContext
{
    /**
     * @param  list<string>  $representedSiteIdentifiers
     * @param  list<string>  $completeSiteIdentifiers
     */
    public function __construct(
        public ?int $initiatedByUserId = null,
        public ?string $originalFilename = null,
        public ?string $contentSha256 = null,
        public array $representedSiteIdentifiers = [],
        public SourceImportScope $scope = SourceImportScope::PartialFilteredExport,
        public array $completeSiteIdentifiers = [],
    ) {}
}
