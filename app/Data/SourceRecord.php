<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/** Transport-independent, validated source row. */
readonly class SourceRecord
{
    /** @param array<string, int|float|string> $products */
    public function __construct(
        public string $callNumber,
        public string $siteIdentifier,
        public string $plotReference,
        public string $callType,
        public ?string $jobStage,
        public ?CarbonImmutable $completedDate,
        public array $products = [],
        public ?CarbonImmutable $sourceUpdatedAt = null,
    ) {}
}
