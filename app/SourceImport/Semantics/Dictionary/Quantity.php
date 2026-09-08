<?php

namespace App\SourceImport\Semantics\Dictionary;

final class Quantity
{
    public const MAX_UNITS = 999999999999;

    public static function units(string|int|float|bool|null $raw): ?int
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return 0;
        }
        if (is_bool($raw) || (is_float($raw) && ! is_finite($raw))) {
            return null;
        }
        // Float-to-string casts depend on PHP's global precision setting. Accept a
        // float only when the three-place decimal round-trips to that exact float;
        // never round an excess-precision value into an approved quantity.
        $text = is_float($raw) ? sprintf('%.3F', $raw) : trim((string) $raw);
        if (is_float($raw) && (float) $text !== $raw) {
            return null;
        }
        if (! preg_match('/^([0-9]+)(?:\.([0-9]{1,3}))?$/D', $text, $m)) {
            return null;
        }
        $whole = ltrim($m[1], '0');
        if (strlen($whole) > 9) {
            return null;
        }

        return ((int) $whole * 1000) + (int) str_pad($m[2] ?? '', 3, '0');
    }

    public static function decimal(int $units): string
    {
        return intdiv($units, 1000).'.'.str_pad((string) ($units % 1000), 3, '0', STR_PAD_LEFT);
    }
}
