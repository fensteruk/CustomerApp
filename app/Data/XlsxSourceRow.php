<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class XlsxSourceRow
{
    /**
     * @param  array<string, int|float|string|null>  $products
     * @param  list<array{code: string, message: string, blocking: bool}>  $errors
     * @param  list<array{code: string, message: string, blocking: bool}>  $warnings
     */
    public function __construct(
        public int $rowNumber,
        public string $callNumber,
        public string $sourceSiteKey,
        public ?string $sourceSiteName,
        public string $plotReference,
        public string $callType,
        public ?string $jobStage,
        public ?CarbonImmutable $completedDate,
        public array $products,
        public ?CarbonImmutable $sourceUpdatedAt = null,
        public array $errors = [],
        public array $warnings = [],
        public ?bool $completionFlag = null,
        public ?CarbonImmutable $operationalTargetDate = null,
    ) {}

    public function toSourceRecord(): SourceRecord
    {
        return new SourceRecord(
            $this->callNumber,
            $this->sourceSiteKey,
            $this->plotReference,
            $this->callType,
            $this->jobStage,
            $this->completedDate,
            collect($this->products)->map(fn ($quantity): float => (float) ($quantity ?? 0))->all(),
            $this->sourceUpdatedAt,
            $this->rowNumber,
            $this->completionFlag,
            $this->operationalTargetDate,
        );
    }

    public function hasBlockingErrors(): bool
    {
        return collect($this->errors)->contains(fn (array $error): bool => $error['blocking']);
    }

    public function isMappedBlank(): bool
    {
        return $this->callNumber === ''
            && $this->sourceSiteKey === ''
            && $this->plotReference === ''
            && $this->callType === ''
            && $this->completedDate === null
            && $this->completionFlag === null
            && $this->operationalTargetDate === null
            && collect($this->products)->every(fn ($quantity): bool => $quantity === null || (is_numeric($quantity) && (float) $quantity === 0.0));
    }
}
