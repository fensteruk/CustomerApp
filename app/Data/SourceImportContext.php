<?php

namespace App\Data;

readonly class SourceImportContext
{
    /** @param list<string> $representedSiteIdentifiers */
    public function __construct(
        public ?int $initiatedByUserId = null,
        public ?string $originalFilename = null,
        public ?string $contentSha256 = null,
        public array $representedSiteIdentifiers = [],
    ) {}
}
