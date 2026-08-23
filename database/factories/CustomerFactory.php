<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Issuer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issuer_code' => Issuer::factory(),
            'doc_type' => Customer::DOC_DNI,
            'doc_number' => fake()->unique()->numerify('########'),
            'name' => fake()->name(),
        ];
    }

    public function forIssuer(Issuer $issuer): static
    {
        return $this->state(fn (): array => ['issuer_code' => $issuer->code]);
    }
}
