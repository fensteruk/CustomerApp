<?php

namespace App\SourceImport\Knowledge;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Metadata only. No deletion operation, scheduler or production disposal grant. */
final class RetentionPolicy
{
    public function due(string $kind, CarbonImmutable $anchor): CarbonImmutable
    {
        return match ($kind) {
            'workbook' => $anchor->addDays(30),
            'observation', 'preview_payload' => $anchor->addDays(7),
            'preview' => $anchor->addHours(24),
            'profile_review' => $anchor->addMonthsNoOverflow(12),
            'knowledge_history' => $anchor->addMonthsNoOverflow(24),
            default => throw new InvalidArgumentException('unsupported_retention_class'),
        };
    }

    public function eligible(?CarbonImmutable $due, CarbonImmutable $now, bool $hold, bool $activeDependency): bool
    {
        return $due !== null && $now->greaterThanOrEqualTo($due) && ! $hold && ! $activeDependency;
    }

    public function automaticDisposalEnabled(): bool
    {
        return false;
    }
}
