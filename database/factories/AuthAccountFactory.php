<?php

namespace Database\Factories;

use App\Models\AuthAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuthAccount>
 */
class AuthAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'login' => Str::lower(fake()->unique()->userName()),
            'password' => Str::password(32),
            'user_code' => User::factory(),
            'is_active' => true,
            'last_login_at' => null,
        ];
    }
}
