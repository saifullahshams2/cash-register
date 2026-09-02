<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default Admin User
        User::firstOrCreate(
            ['email' => 'admin@pos.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        // Default Cashier User
        User::firstOrCreate(
            ['email' => 'cashier@pos.test'],
            [
                'name' => 'Cashier 01',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CASHIER,
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            ProductSeeder::class,
        ]);
    }
}
