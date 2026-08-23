<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Issuer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issuer_code' => Issuer::factory(),
            'name' => fake()->unique()->city(),
            'sunat_establishment_code' => '0000',
            'is_active' => true,
        ];
    }
}
