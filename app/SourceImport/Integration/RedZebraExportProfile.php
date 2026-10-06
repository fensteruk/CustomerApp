<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

/** Controlled alternate XLSX vocabulary, approved by management on 6 October 2026. */
final class RedZebraExportProfile
{
    public const VERSION = 'customerapp.redzebra-export-profiles.v2';

    public const ALTERNATE = 'redzebra-master-optional-call.v2';

    public const PLOT_REGISTER = 'redzebra-plot-register.v1';

    private const HEADERS = ['call', 'call type', 'site name', 'customer name', 'arrival', 'complete date', 'customer'];

    public function recognise(array $region, string $format): ?string
    {
        $range = $region['headers']['range'] ?? null;
        $paths = $region['headers']['paths'] ?? [];
        usort($paths, fn ($a, $b): int => $a['column'] <=> $b['column']);
        if ($range !== null && $range['start_row'] === $range['end_row'] && count($paths) === 2
            && $range['end_column'] - $range['start_column'] === 1
            && $paths[0]['column'] === $range['start_column'] && $paths[1]['column'] === $range['end_column']
            && ($region['orientation'] ?? null) === 'vertical_records'
            && in_array($format, ['xlsx', 'xls', 'csv'], true)) {
            $dictionary = new CustomerAppDictionary;
            $roles = array_map(fn ($path) => $dictionary->field($path['label'])->value, $paths);
            if (in_array('source_customer_code', $roles, true) && in_array('plot_reference', $roles, true)
                && array_filter($paths, fn ($path) => count($path['parts']) !== 1) === []) {
                return self::PLOT_REGISTER;
            }
        }
        if ($format !== 'xlsx' || $range === null || $range['start_row'] !== $range['end_row']
            || count($paths) !== $range['end_column'] - $range['start_column'] + 1
            || ($region['orientation'] ?? null) !== 'vertical_records') {
            return null;
        }
        $labels = array_map(fn ($path) => strtolower(trim($path['label'])), $paths);
        // Only the two call-specific columns may be omitted from the approved signature.
        $expected = array_values(array_filter(self::HEADERS, fn ($header) => ! in_array($header, ['call', 'call type'], true) || in_array($header, $labels, true)));
        if ($labels !== $expected) {
            return null;
        }
        foreach ($paths as $index => $path) {
            if ($path['column'] !== $range['start_column'] + $index
                || $labels[$index] !== $expected[$index]
                || count($path['parts']) !== 1) {
                return null;
            }
        }

        return self::ALTERNATE;
    }

    public function role(?string $profile, string $header): ?string
    {
        if ($profile !== self::ALTERNATE) {
            return null;
        }

        return match (strtolower(trim($header))) {
            'call' => 'call_reference',
            'customer' => 'source_customer_code',
            'site name' => 'plot_reference',
            'customer name', 'arrival', 'complete date' => 'ignored',
            default => null,
        };
    }
}
