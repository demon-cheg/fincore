<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency' => fake()->randomElement([
                'EUR',
                'USD',
                'GBP',
            ]),
            'balance_minor' => fake()->numberBetween(10_000, 500_000),
            'status' => AccountStatus::Active,
        ];
    }
}