<?php

namespace App\SourceImport\Integration;

/** Controlled alternate XLSX vocabulary, approved by management on 6 October 2026. */
final class RedZebraExportProfile
{
    public const VERSION = 'customerapp.redzebra-export-profiles.v1';

    public const ALTERNATE = 'redzebra-seven-column.v1';

    private const HEADERS = ['call', 'call type', 'site name', 'customer name', 'arrival', 'complete date', 'customer'];

    public function recognise(array $region, string $format): ?string
    {
        $range = $region['headers']['range'] ?? null;
        $paths = $region['headers']['paths'] ?? [];
        if ($format !== 'xlsx' || $range === null || $range['start_row'] !== $range['end_row']
            || $range['end_column'] - $range['start_column'] !== 6 || count($paths) !== 7
            || ($region['orientation'] ?? null) !== 'vertical_records') {
            return null;
        }
        usort($paths, fn ($a, $b): int => $a['column'] <=> $b['column']);
        foreach ($paths as $index => $path) {
            if ($path['column'] !== $range['start_column'] + $index
                || strtolower(trim($path['label'])) !== self::HEADERS[$index]
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
