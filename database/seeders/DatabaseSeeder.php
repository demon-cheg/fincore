<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@fincore.local',
            'password' => Hash::make('DemoPassword123!'),
        ]);

        Account::factory()->for($demoUser)->create([
            'currency' => 'EUR',
            'balance_minor' => 125_000,
            'status' => AccountStatus::Active,
        ]);

        Account::factory()->for($demoUser)->create([
            'currency' => 'USD',
            'balance_minor' => 82_500,
            'status' => AccountStatus::Active,
        ]);

        User::factory()
            ->count(4)
            ->has(Account::factory()->count(2))
            ->create();
    }
}