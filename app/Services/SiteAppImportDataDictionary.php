<?php

namespace App\Services;

use App\Enums\CallOffServiceType;

class SiteAppImportDataDictionary
{
    public function semanticVersion(): int
    {
        return (int) config('siteapp_import.semantic_version', 1);
    }

    /** @return array<string, array{description: string, portal_service: string|null}> */
    public function callTypes(): array
    {
        return config('siteapp_import.call_types', []);
    }

    /** @return array{description: string, portal_service: string|null}|null */
    public function callType(string $code): ?array
    {
        return $this->callTypes()[$this->normaliseCode($code)] ?? null;
    }

    public function isKnownCallType(string $code): bool
    {
        return $this->callType($code) !== null;
    }

    public function portalServiceFor(string $code): ?CallOffServiceType
    {
        $value = $this->callType($code)['portal_service'] ?? null;

        return $value === null ? null : CallOffServiceType::tryFrom($value);
    }

    public function likelyCallTypeCorrection(string $code): ?string
    {
        return config('siteapp_import.likely_call_type_typos.'.$this->normaliseCode($code));
    }

    /** @return array<string, array{description: string, group: 'WINDOWS'|'DOORS'|'EXCLUDED', customer_rollup: bool, bf_lead_time: bool}> */
    public function products(): array
    {
        return config('siteapp_import.products', []);
    }

    /** @return array{description: string, group: 'WINDOWS'|'DOORS'|'EXCLUDED', customer_rollup: bool, bf_lead_time: bool}|null */
    public function product(string $code): ?array
    {
        return $this->products()[$this->normaliseCode($code)] ?? null;
    }

    public function isKnownProduct(string $code): bool
    {
        return $this->product($code) !== null;
    }

    /**
     * @param  iterable<mixed, mixed>  $products
     * @return list<array{code: string, label: string, quantity: float}>
     */
    public function customerRollups(iterable $products): array
    {
        $quantities = $this->normaliseQuantities($products);
        $totals = [];

        foreach ($this->products() as $code => $definition) {
            if (! $definition['customer_rollup']) {
                continue;
            }

            $group = $definition['group'];
            $totals[$group] = ($totals[$group] ?? 0.0) + ($quantities[$code] ?? 0.0);
        }

        return collect(config('siteapp_import.customer_rollups', []))
            ->map(fn (string $label, string $group): array => [
                'code' => $group,
                'label' => $label,
                'quantity' => (float) ($totals[$group] ?? 0),
            ])
            ->filter(fn (array $rollup): bool => $rollup['quantity'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  iterable<mixed, mixed>  $products
     * @return list<array{code: string, description: string, group: string, quantity: float, customer_rollup: bool, bf_lead_time: bool}>
     */
    public function officeDetails(iterable $products): array
    {
        $quantities = $this->normaliseQuantities($products);

        return collect($this->products())
            ->map(function (array $definition, string $code) use ($quantities): array {
                return [
                    'code' => $code,
                    'description' => $definition['description'],
                    'group' => $definition['group'],
                    'quantity' => (float) ($quantities[$code] ?? 0),
                    'customer_rollup' => $definition['customer_rollup'],
                    'bf_lead_time' => $definition['bf_lead_time'],
                ];
            })
            ->filter(fn (array $detail): bool => $detail['quantity'] > 0)
            ->values()
            ->all();
    }

    /** @param iterable<mixed, mixed> $products */
    public function hasPositiveBifold(iterable $products): bool
    {
        $quantities = $this->normaliseQuantities($products);

        return collect($this->products())->contains(
            fn (array $definition, string $code): bool => $definition['bf_lead_time'] && ($quantities[$code] ?? 0) > 0,
        );
    }

    public function isUnconfirmedHeader(string $header): bool
    {
        return array_key_exists($this->normaliseHeader($header), config('siteapp_import.unconfirmed_fields', []));
    }

    public function unconfirmedHeaderReason(string $header): ?string
    {
        return config('siteapp_import.unconfirmed_fields.'.$this->normaliseHeader($header));
    }

    private function normaliseCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    private function normaliseHeader(string $header): string
    {
        return trim((string) preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($header))));
    }

    /** @param iterable<mixed, mixed> $products @return array<string, float> */
    private function normaliseQuantities(iterable $products): array
    {
        $quantities = [];

        foreach ($products as $key => $product) {
            if (is_object($product) && isset($product->product_code, $product->quantity)) {
                $code = $this->normaliseCode((string) $product->product_code);
                $quantity = (float) $product->quantity;
            } elseif (is_array($product) && isset($product['quantity'])) {
                $code = $this->normaliseCode((string) ($product['code'] ?? $product['product_code'] ?? $key));
                $quantity = (float) $product['quantity'];
            } else {
                $code = $this->normaliseCode((string) $key);
                $quantity = (float) $product;
            }

            if ($code !== '') {
                $quantities[$code] = ($quantities[$code] ?? 0.0) + $quantity;
            }
        }

        return $quantities;
    }
}
