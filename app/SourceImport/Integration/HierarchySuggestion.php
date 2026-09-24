<?php

namespace App\SourceImport\Integration;

/** Management-confirmed source pattern offered only as an Office review suggestion. */
final class HierarchySuggestion
{
    public function forIssue(string $customerCode, mixed $raw): ?array
    {
        if (! is_string($raw)) {
            return null;
        }
        if ($customerCode !== 'FNA2664'
            || ! preg_match('/^Little Cotton Farm 117-144\.\.\.- Baker Estates Ltd([0-9]+)$/D', trim($raw), $match)) {
            return null;
        }

        return ['customer' => 'Baker Estates Ltd', 'site' => 'Little Cotton Farm 117-144',
            'plot' => SourceIdentity::plotReference($match[1]), 'requires_office_confirmation' => true];
    }
}
