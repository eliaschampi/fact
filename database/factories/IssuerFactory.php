<?php

namespace Database\Factories;

use App\Models\Issuer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issuer>
 */
class IssuerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ruc' => fake()->unique()->numerify('20#########'),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->optional()->company(),
            'tax_regime' => Issuer::REGIME_GENERAL,
            'address_line' => fake()->streetAddress(),
            'ubigeo' => '150101',
            'department' => 'LIMA',
            'province' => 'LIMA',
            'district' => 'LIMA',
            'capabilities' => [
                'invoice' => true,
                'receipt' => true,
                'credit_note' => true,
                'gre_sender' => true,
            ],
            'environment' => Issuer::ENVIRONMENT_BETA,
        ];
    }
}
