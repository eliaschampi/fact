<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\User;
use App\Support\LimaDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_code' => Branch::factory(),
            'issuer_code' => fn (array $attributes): string => (string) Branch::query()
                ->whereKey($attributes['branch_code'])
                ->value('issuer_code'),
            'user_code' => User::factory(),
            'purchase_number' => 'PUR-'.fake()->unique()->numerify('######'),
            'status' => Purchase::STATUS_IN_TRANSIT,
            'ordered_at' => LimaDate::businessDate(),
        ];
    }
}
