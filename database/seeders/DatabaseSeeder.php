<?php

namespace Database\Seeders;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Admin');

        // Intentionally unlinked (user_id = null): this is the admin gateway
        // device and may subscribe to any user's channel. Do not assign a
        // user_id here unless you want it scoped to a single owner.
        DeviceToken::create([
            'name' => 'smsGateway Samsung S22',
            'type' => 'android',
            'token' => Str::random(16),
            'is_active' => true,
        ]);
    }
}
