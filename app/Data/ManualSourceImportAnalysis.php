<?php

namespace App\Data;

readonly class ManualSourceImportAnalysis
{
    /**
     * @param  array<string, int>  $summary
     * @param  list<array<string, mixed>>  $rows
     * @param  list<SourceRecord>  $records
     * @param  list<string>  $representedSiteKeys
     */
    public function __construct(
        public array $summary,
        public array $rows,
        public array $records,
        public array $representedSiteKeys,
        public int $blockingErrorCount,
        public string $sourceFingerprint,
    ) {}
}
