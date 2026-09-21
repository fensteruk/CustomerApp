<?php

namespace App\Presenters;

use App\Models\ProjectedPlotProduct;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Illuminate\Support\Collection;

final readonly class CustomerProductPresenter
{
    public function __construct(private CustomerAppDictionary $dictionary) {}

    /**
     * Presentation only: never use these rows for projection, totals or lead-time decisions.
     *
     * @param  iterable<ProjectedPlotProduct|array{code: string, quantity: string}>  $products
     * @return Collection<int, array{label: string, quantity: string}>
     */
    public function present(iterable $products): Collection
    {
        $rows = collect();

        foreach ($products as $product) {
            $code = $product instanceof ProjectedPlotProduct ? $product->product_code : $product['code'];
            $quantity = (string) $product['quantity'];

            // Keep the existing positive-only customer display, without changing stored facts.
            if ((float) $quantity <= 0) {
                continue;
            }

            $meaning = $this->dictionary->product($code, $quantity);
            if (($meaning->match['group'] ?? null) === 'EXCLUDED') {
                continue;
            }

            $rows->push([
                'label' => $meaning->match['description'] ?? 'Product ('.$code.')',
                // Stored decimal:3 strings retain their exact value; no float formatting/rounding.
                'quantity' => str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity,
            ]);
        }

        return $rows;
    }
}
