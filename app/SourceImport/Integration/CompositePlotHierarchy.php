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
        // Compact RedZebra form: "Customer - Site-PlotRef". Split at the
        // final hyphen so the site can contain hyphens without guessing.
        if (preg_match('/^(.+?)\s+-\s+(.+)-([0-9]+)$/uD', $trimmed, $compact)) {
            $customer = trim($compact[1]);
            $site = trim($compact[2]);
            $plotRef = trim($compact[3]);
            if ($customer !== '' && $site !== '' && $plotRef !== '') {
                foreach ([$customer, $site, $plotRef] as $part) {
                    if (mb_strlen($part) > 200 || preg_match('/[\x00-\x1f\x7f<>]/', $part)) {
                        return ['valid' => false, 'issue' => 'HIERARCHY_COMPONENT_INVALID', 'raw' => $raw];
                    }
                }

                return ['valid' => true, 'customer' => $customer, 'site' => $site,
                    'plot_source' => $plotRef, 'plot' => SourceIdentity::plotReference($plotRef), 'raw' => $raw];
            }
        }
        $parts = preg_split('/\s+[-\x{2013}\x{2014}]\s+/u', $trimmed);
        // Previously approved exact unspaced three-component format. Restrict
        // it to precisely two separators and an explicit Plot label.
        if ($parts !== false && count($parts) === 1 && substr_count($trimmed, '-') === 2
            && preg_match('/^[^-]+-[^-]+-Plot\s+.+$/iuD', $trimmed)) {
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
