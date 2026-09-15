<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;

final class SourceIdentity
{
    public static function plotReference(string|int $reference): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $reference));
    }

    public static function sourceRow(string $namespace, string $callNumber): string
    {
        return Canonical::hash([$namespace, $callNumber]);
    }

    public static function visit(string $namespace, string $callNumber, string $callType): string
    {
        return Canonical::hash([$namespace, $callNumber, $callType]);
    }

    public static function plot(string $namespace, int $siteId, string $plotReference): string
    {
        return 'wald:'.Canonical::hash([$namespace, $siteId, $plotReference]);
    }
}
