<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CraftMasterGeneral,
            'admin_permissions' => null,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->call(MenuDevelopmentSeeder::class);
        $this->call(PartnerKitchenPriceSeeder::class);
    }
}
