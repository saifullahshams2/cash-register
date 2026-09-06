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
        if (app()->isProduction()) {
            return;
        }

        // Default Admin User (Local Testing Only)
        $admin = User::updateOrCreate(
            ['email' => 'admin@pos.test'],
            [
                'username' => 'admin',
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        // Default Cashier User (Local Testing Only)
        $cashier = User::updateOrCreate(
            ['email' => 'cashier@pos.test'],
            [
                'username' => 'cashier',
                'name' => 'Cashier 01',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $cashier->forceFill(['role' => User::ROLE_CASHIER])->save();

        $this->call([
            ProductSeeder::class,
        ]);
    }
}
