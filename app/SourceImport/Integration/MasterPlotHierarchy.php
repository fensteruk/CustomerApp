<?php

namespace App\SourceImport\Integration;

/** Customer/site labels from a master plot section; column AA owns plot identity. */
final class MasterPlotHierarchy
{
    public function parse(mixed $raw, ?string $customerNumber = null): array
    {
        if (! is_string($raw)) {
            return ['valid' => false, 'issue' => 'MASTER_HIERARCHY_VALUE_INVALID'];
        }

        // Normalize line wraps and spacing from the repeated site-table layout.
        $value = preg_replace('/\s+/u', ' ', trim($raw)) ?? trim($raw);
        // Compact RedZebra layout: "Customer - Site-PlotRef". The final
        // hyphen introduces the plot; only the customer/site separator is spaced.
        if (preg_match('/^(.+?)\s+-\s+(.+)-([0-9]+)$/uD', $value, $compact)
            && trim($compact[1]) !== '' && trim($compact[2]) !== '' && trim($compact[3]) !== '') {
            return ['valid' => true, 'customer' => trim($compact[1]), 'site' => trim($compact[2])];
        }
        if (! preg_match('/^(.*)\s*[-\x{2013}\x{2014}]\s*Plot\s+(.+)$/iu', $value, $match)) {
            return ['valid' => false, 'issue' => 'MASTER_HIERARCHY_PLOT_LABEL_MISSING'];
        }

        // Split after the customer once, retaining internal hyphens in a site name.
        $prefix = trim($match[1]);
        $parts = preg_split('/\s+[-\x{2013}\x{2014}]\s+/u', $prefix, 2);
        if ($parts === false || count($parts) !== 2) {
            // Approved compact customer-site separator format. A compact site
            // name containing another hyphen remains unresolved instead of guessed.
            if (preg_match_all('/[-\x{2013}\x{2014}]/u', $prefix) !== 1) {
                return ['valid' => false, 'issue' => 'MASTER_HIERARCHY_CUSTOMER_SITE_UNRESOLVED'];
            }
            $parts = preg_split('/[-\x{2013}\x{2014}]/u', $prefix, 2);
        }
        if ($parts === false || count($parts) !== 2) {
            return ['valid' => false, 'issue' => 'MASTER_HIERARCHY_CUSTOMER_SITE_UNRESOLVED'];
        }

        [$customer, $site] = array_map('trim', $parts);
        if ($customer === '' || $site === '' || trim($match[2]) === '') {
            return ['valid' => false, 'issue' => 'MASTER_HIERARCHY_COMPONENT_EMPTY'];
        }
        foreach ([$customer, $site] as $component) {
            if (mb_strlen($component) > 200 || preg_match('/[\x00-\x1f\x7f<>]/', $component)) {
                return ['valid' => false, 'issue' => 'MASTER_HIERARCHY_COMPONENT_INVALID'];
            }
        }

        return ['valid' => true, 'customer' => $customer, 'site' => $site];
    }
}
