<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveProduct
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     is_active: bool,
     *     variants: list<array{code?: string|null, sku: string, name?: string|null, unit_code: string, tax_affectation: string, price: string}>
     * }  $attributes
     */
    public function handle(Product $product, array $attributes): Product
    {
        if ($product->exists && ! $product->isOperable() && ! $attributes['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'Un producto inactivo no se opera. Reactívalo para guardar cambios.',
            ]);
        }

        try {
            DB::transaction(function () use ($product, $attributes): void {
                $product->fill([
                    'name' => $attributes['name'],
                    'description' => $attributes['description'] ?? null,
                    'is_active' => $attributes['is_active'],
                ])->save();

                foreach ($attributes['variants'] as $variant) {
                    $values = [
                        'sku' => $variant['sku'],
                        'name' => $variant['name'] ?? null,
                        'unit_code' => $variant['unit_code'],
                        'tax_affectation' => $variant['tax_affectation'],
                        'price' => Money::normalize($variant['price']),
                    ];

                    if (($variant['code'] ?? null) !== null) {
                        $product->variants()
                            ->findOrFail($variant['code'])
                            ->update($values);

                        continue;
                    }

                    $product->variants()->create([
                        ...$values,
                        'issuer_code' => $product->issuer_code,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'variants' => 'El SKU ya está en uso por otra variante.',
            ]);
        }

        return $product->refresh()->loadMissing('variants');
    }
}
