<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

/** Controlled roles establish capabilities; absence never invents a visit. */
final class PlotRegisterCapabilities
{
    public const VERSION = 'customerapp.plot-register-capabilities.v1';

    public static function forRoles(array $roles): array
    {
        return ['plot_register' => count($roles['source_customer_code'] ?? []) === 1 && count($roles['plot_reference'] ?? []) === 1,
            'call_reference' => count($roles['call_reference'] ?? []) === 1,
            'existing_calloff_data' => count($roles['call_reference'] ?? []) === 1 && count($roles['call_type'] ?? []) === 1,
            'call_type' => count($roles['call_type'] ?? []) === 1];
    }

    public static function category(mixed $type): string
    {
        if ($type === null || is_string($type) && trim($type) === '') {
            return 'BASE_PLOT_ROW';
        }
        if (! is_string($type)) {
            return 'UNKNOWN_NONBLANK_CALLOFF_ROW';
        }
        $meaning = (new CustomerAppDictionary)->callType($type);
        if (is_string($type) && CustomerAppDictionary::excludesCallType($type)) {
            return 'APPROVED_EXCLUDED_ROW';
        }
        if ($meaning->isResolved() && $meaning->value === null) {
            return 'BASE_PLOT_ROW';
        }

        return $meaning->isResolved() ? 'RECOGNISED_CALLOFF_ROW' : 'UNKNOWN_NONBLANK_CALLOFF_ROW';
    }

    public static function summary(array $rows): array
    {
        $base = $called = $unknown = [];
        $counts = ['base_plot_rows' => 0, 'recognised_calloff_rows' => 0, 'unknown_call_type_rows' => 0];
        foreach ($rows as $row) {
            $category = ($row['issue'] ?? null) === 'UNKNOWN_CALL_TYPE' ? 'UNKNOWN_NONBLANK_CALLOFF_ROW' : self::category($row['call_type'] ?? null);
            $key = ($row['source_identity_hash'] ?? '')."\0".($row['parsed_plot'] ?? '');
            if ($category === 'BASE_PLOT_ROW') {
                $counts['base_plot_rows']++;
                if ($row['parsed_plot'] !== null) {
                    $base[$key] = true;
                }
            } elseif ($category === 'RECOGNISED_CALLOFF_ROW') {
                $counts['recognised_calloff_rows']++;
                $called[$key] = true;
            } elseif ($category === 'UNKNOWN_NONBLANK_CALLOFF_ROW') {
                $counts['unknown_call_type_rows']++;
                $unknown[$key] = true;
            }
        }

        return [...$counts, 'plots_without_source_calloff_in_export' => count(array_diff_key($base, $called, $unknown))];
    }
}
