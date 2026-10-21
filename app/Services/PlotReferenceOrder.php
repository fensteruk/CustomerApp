<?php

namespace App\Services;

/**
 * Customer-facing plot order.
 *
 * Users read a plot reference such as "Plot 001" as the number 1, so plots sort by the last
 * run of digits in the reference. References without a number follow the numbered plots in
 * either direction, in natural order.
 */
final class PlotReferenceOrder
{
    public static function number(string $reference): ?int
    {
        if (! preg_match_all('/\d+/', $reference, $matches)) {
            return null;
        }

        return (int) end($matches[0]);
    }

    public static function compare(string $left, string $right, bool $descending = false): int
    {
        $leftNumber = self::number($left);
        $rightNumber = self::number($right);

        if (($leftNumber === null) !== ($rightNumber === null)) {
            return $leftNumber === null ? 1 : -1;
        }

        $order = ($leftNumber <=> $rightNumber) ?: strnatcasecmp($left, $right);

        return $descending ? -$order : $order;
    }
}
