<?php

namespace App\SourceImport\Integration;

/** Exact customer/site removal for Office-reviewed source rows; never fuzzy. */
final class ReviewedPlotDerivation
{
    public function derive(mixed $raw, string $customer, string $site): array
    {
        if (! is_string($raw) || trim($customer) === '' || trim($site) === '') {
            return ['plot' => null, 'issue' => 'CUSTOMER_SITE_OR_SOURCE_MISSING'];
        }
        $customerPattern = $this->namePattern($customer);
        $sitePattern = $this->namePattern($site);
        $separator = '\s+[-\x{2013}\x{2014}]\s+';
        if (! preg_match('/^\s*'.$customerPattern.$separator.$sitePattern.$separator.'(.+?)\s*$/iu', $raw, $match)) {
            return ['plot' => null, 'issue' => 'CUSTOMER_SITE_TEXT_MISMATCH'];
        }
        $remaining = trim($match[1]);
        if (preg_match('/^Plot\s+(.+)$/iu', $remaining, $plot)) {
            $remaining = trim($plot[1]);
        }
        if ($remaining === '' || mb_strlen($remaining) > 200
            || preg_match('/[\x00-\x1f\x7f<>\x{2013}\x{2014}-]/u', $remaining)) {
            return ['plot' => null, 'issue' => 'MULTIPLE_OR_INVALID_PLOT_FRAGMENTS'];
        }

        return ['plot' => SourceIdentity::plotReference($remaining), 'issue' => null];
    }

    private function namePattern(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name));

        return implode('\s+', array_map(fn (string $word): string => preg_quote($word, '/'), $words));
    }
}
