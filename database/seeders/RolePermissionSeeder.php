<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::firstOrCreate(['name' => 'send-sms', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'approve-users', 'guard_name' => 'api']);

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'api']);
        $admin->syncPermissions(['send-sms', 'approve-users']);

        $client = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'api']);
        $client->syncPermissions(['send-sms']);

        $appClient = Role::firstOrCreate(['name' => 'AppClient', 'guard_name' => 'api']);
        $appClient->syncPermissions(['send-sms']);
    }
}
