<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
        Role::create(['name' => 'Client', 'guard_name' => 'api'])->syncPermissions(['send-sms']);
        Role::create(['name' => 'AppClient', 'guard_name' => 'api'])->syncPermissions(['send-sms']);
    }

    public function test_newly_created_user_has_no_role_by_default(): void
    {
        $user = User::factory()->create([
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);

        $this->assertFalse($user->hasAnyRole(['Client', 'Admin', 'AppClient']));
    }

    public function test_user_without_role_cannot_send_sms(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello',
        ]);

        $response->assertStatus(403);
    }

    public function test_approved_client_can_send_sms(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello',
        ]);

        $response->assertStatus(202);
    }

    public function test_app_client_role_can_be_assigned(): void
    {
        $user = User::factory()->create([
            'name' => 'App Client',
            'email' => 'appclient@example.com',
        ]);
        $user->assignRole('AppClient');

        $this->assertTrue($user->hasRole('AppClient'));
        $this->assertTrue($user->hasPermissionTo('send-sms'));
    }

    public function test_app_client_can_send_sms(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->assignRole('AppClient');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello from app',
        ]);

        $response->assertStatus(202);
    }
}
