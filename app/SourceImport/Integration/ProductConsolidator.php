<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Semantics\Dictionary\Quantity;

final class ProductConsolidator
{
    /**
     * @return array{products: array<string, array<string, string>>, conflicts: array<string, array<string, list<string>>>}
     */
    public function consolidate(array $rows): array
    {
        $assertions = [];

        foreach ($rows as $row) {
            if ($row['excluded']) {
                continue;
            }

            $plot = $row['facts']['plot'];
            foreach ($row['facts']['products'] as $code => $quantity) {
                $units = Quantity::units($quantity);
                if ($units === null) {
                    continue;
                }

                $assertions[$plot][$code][(string) $units][] = $row['facts']['call_number'];
            }
        }

        $products = [];
        $conflicts = [];
        foreach ($assertions as $plot => $codes) {
            foreach ($codes as $code => $values) {
                if (count($values) > 1) {
                    $conflicts[$plot][$code] = array_values(array_unique(array_merge(...array_values($values))));

                    continue;
                }

                $units = (int) array_key_first($values);
                $products[$plot][$code] = Quantity::decimal($units);
            }
        }

        ksort($products, SORT_STRING);
        foreach ($products as &$items) {
            ksort($items, SORT_STRING);
        }
        unset($items);

        return ['products' => $products, 'conflicts' => $conflicts];
    }
}
