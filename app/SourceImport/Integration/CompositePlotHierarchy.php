<?php

namespace App\SourceImport\Integration;

/** Exact Customer / Site / Plot structure carried in RedZebra Plot Ref. */
final class CompositePlotHierarchy
{
    public const VERSION = 'customerapp.composite-plot-hierarchy.v2';

    public function parse(mixed $value, ?string $customerCode = null): array
    {
        if (! is_string($value)) {
            return ['valid' => false, 'issue' => 'HIERARCHY_VALUE_INVALID'];
        }

        $raw = $value;
        $trimmed = trim($raw);
        // The confirmed Northam source format has one spaced separator and a final compact plot separator.
        if ($customerCode === 'FNA2561'
            && preg_match('/^Vistry - Northam PH3-([0-9]+)$/D', $trimmed, $match)) {
            return ['valid' => true, 'customer' => 'Vistry', 'site' => 'Northam PH3',
                'plot_source' => $match[1], 'plot' => SourceIdentity::plotReference($match[1]), 'raw' => $raw];
        }
        $parts = preg_split('/\s+[-\x{2013}\x{2014}]\s+/u', $trimmed);
        // Approved compact RedZebra variant: exactly two ASCII separators and a Plot component.
        if ($parts !== false && count($parts) === 1 && ! preg_match('/\s+[-\x{2013}\x{2014}]\s+/u', $trimmed)) {
            $parts = explode('-', $trimmed);
        }
        if ($parts === false || count($parts) !== 3) {
            return ['valid' => false, 'issue' => 'HIERARCHY_COMPONENT_COUNT', 'raw' => $raw];
        }

        $parts = array_map('trim', $parts);
        if (in_array('', $parts, true)) {
            return ['valid' => false, 'issue' => 'HIERARCHY_EMPTY_COMPONENT', 'raw' => $raw];
        }
        foreach ($parts as $part) {
            if (mb_strlen($part) > 200 || preg_match('/[\x00-\x1f\x7f<>]/', $part)) {
                return ['valid' => false, 'issue' => 'HIERARCHY_COMPONENT_INVALID', 'raw' => $raw];
            }
        }
        if (! preg_match('/^Plot\s+(.+)$/iu', $parts[2], $plot) || trim($plot[1]) === '') {
            return ['valid' => false, 'issue' => 'HIERARCHY_PLOT_INVALID', 'raw' => $raw];
        }

        return ['valid' => true, 'customer' => $parts[0], 'site' => $parts[1],
            'plot_source' => $parts[2], 'plot' => SourceIdentity::plotReference($plot[1]), 'raw' => $raw];
    }
}
