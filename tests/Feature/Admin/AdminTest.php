<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
        Permission::create(['name' => 'approve-users', 'guard_name' => 'api']);

        Role::create(['name' => 'Admin', 'guard_name' => 'api'])->syncPermissions(['send-sms', 'approve-users']);
        Role::create(['name' => 'Client', 'guard_name' => 'api'])->syncPermissions(['send-sms']);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Passport::actingAs($admin, [], 'api');

        User::factory(3)->create();

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'data');
    }

    public function test_admin_can_approve_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Passport::actingAs($admin, [], 'api');

        $client = User::factory()->create();

        $response = $this->postJson("/api/v1/admin/users/{$client->id}/approve");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User approved successfully.']);

        $this->assertTrue($client->fresh()->hasRole('Client'));
    }

    public function test_approve_already_approved_user_returns_409(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Passport::actingAs($admin, [], 'api');

        $client = User::factory()->create();
        $client->assignRole('Client');

        $response = $this->postJson("/api/v1/admin/users/{$client->id}/approve");

        $response->assertStatus(409);
    }

    public function test_admin_can_revoke_user_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Passport::actingAs($admin, [], 'api');

        $client = User::factory()->create();
        $client->assignRole('Client');

        $response = $this->postJson("/api/v1/admin/users/{$client->id}/revoke");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User roles revoked successfully.']);

        $this->assertFalse($client->fresh()->hasRole('Client'));
    }

    public function test_revoke_user_without_roles_returns_409(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Passport::actingAs($admin, [], 'api');

        $client = User::factory()->create();

        $response = $this->postJson("/api/v1/admin/users/{$client->id}/revoke");

        $response->assertStatus(409);
    }

    public function test_non_admin_cannot_approve_users(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $client = User::factory()->create();

        $response = $this->postJson("/api/v1/admin/users/{$client->id}/approve");

        $response->assertStatus(403);
    }
}
