<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;

/** A repeated reference withholds call-dependent assertions, never physical inventory. */
final class ScopedCallEvidence
{
    public const VERSION = 'customerapp.scoped-call-evidence.v1';

    public function discover(object $sheet, array $table, array $columns, ?array $logicalRows = null): array
    {
        if (! isset($columns['call_reference'])) {
            return [];
        }
        $groups = [];
        $start = $table['data_start_row'] ?? $table['header_range']['end_row'] + 1;
        $end = $table['data_end_row'] ?? $table['range']['end_row'];
        foreach ($sheet->cells as $number => $cells) {
            if ($number < $start || $number > $end) {
                continue;
            }
            if (($logicalRows[$number]['excluded'] ?? false) === true) {
                continue;
            }
            $raw = fn ($role) => $logicalRows !== null ? ($logicalRows[$number][$role] ?? null)
                : (isset($columns[$role]) ? ($cells[$columns[$role]]->rawValue ?? null) : null);
            $call = $raw('call_reference');
            if ($call === null || is_string($call) && trim($call) === '') {
                continue;
            }
            $groups[(string) $call][] = ['sheet' => $sheet->id, 'row' => (int) $number,
                'customer_code' => $raw('source_customer_code'), 'plot_evidence' => $raw('plot_reference'),
                'call_type' => $raw('call_type')];
        }
        $findings = [];
        foreach ($groups as $call => $rows) {
            if (count($rows) > 1) {
                $finding = ['code' => 'DUPLICATE_CALL_NUMBER', 'dependency' => 'CALL_VISIT_COMPLETION',
                    'call_number' => (string) $call, 'rows' => $rows];
                $findings[(string) $call] = [...$finding, 'hash' => Canonical::hash($finding)];
            }
        }
        ksort($findings, SORT_STRING);

        return $findings;
    }

    public static function marker(?array $finding): array
    {
        return $finding === null ? [] : [['code' => $finding['code'], 'call_number' => $finding['call_number'],
            'group_hash' => $finding['hash'], 'row_count' => count($finding['rows'])]];
    }

    public static function facts(array $row): array
    {
        $facts = $row['facts'];
        if (($row['call_blockers'] ?? []) !== []) {
            foreach (['call_number', 'call_type', 'service', 'complete'] as $key) {
                if (array_key_exists($key, $facts)) {
                    $facts[$key] = null;
                }
            }
        }
        foreach ($row['product_blockers'] ?? [] as $code) {
            unset($facts['products'][$code]);
        }

        return $facts;
    }

    /** Only conflicts involving repeated Call evidence gain fact-level withholding. */
    public static function partitionProducts(array &$rows): array
    {
        $conflicts = (new ProductConsolidator)->consolidate($rows)['conflicts'];
        $withheld = [];
        foreach ($conflicts as $plot => $codes) {
            foreach ($codes as $code => $calls) {
                foreach ($rows as $row) {
                    if (! $row['excluded'] && ($row['call_blockers'] ?? []) !== []
                        && $row['facts']['plot'] === (string) $plot && isset($row['facts']['products'][$code])) {
                        $withheld[$plot][$code] = $calls;
                        break;
                    }
                }
            }
        }
        foreach ($rows as &$row) {
            if ($row['excluded']) {
                continue;
            }
            $codes = array_keys($withheld[$row['facts']['plot']] ?? []);
            if ($codes !== []) {
                $row['product_blockers'] = $codes;
            }
            if (($row['call_blockers'] ?? []) !== [] || $codes !== []) {
                $row['canonical'] = ['observed' => $row['canonical'], 'projection' => self::facts($row),
                    'call_blockers' => $row['call_blockers'] ?? [], 'product_blockers' => $codes];
            }
        }
        unset($row);

        return $withheld;
    }
}
