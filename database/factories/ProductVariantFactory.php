<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_code' => Product::factory(),
            'issuer_code' => fn (array $attributes): string => (string) Product::query()
                ->whereKey($attributes['product_code'])
                ->value('issuer_code'),
            'sku' => fake()->unique()->regexify('[a-z]{5}-[0-9]{4}'),
            'unit_code' => ProductVariant::UNIT_NIU,
            'tax_affectation' => '10',
            'price' => '18.50',
            'cost_price' => '0.00',
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'product_code' => $product->code,
            'issuer_code' => $product->issuer_code,
        ]);
    }
}
