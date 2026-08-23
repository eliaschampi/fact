<?php

namespace Database\Factories;

use App\Models\Issuer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issuer_code' => Issuer::factory(),
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
        ];
    }

    public function forIssuer(Issuer $issuer): static
    {
        return $this->state(fn (): array => ['issuer_code' => $issuer->code]);
    }
}
