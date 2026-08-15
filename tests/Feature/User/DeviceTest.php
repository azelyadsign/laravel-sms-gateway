<?php

namespace Tests\Feature\User;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);

        Role::create(['name' => 'Client', 'guard_name' => 'api'])
            ->syncPermissions(['send-sms']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/user/devices');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/user/devices');

        $response->assertStatus(403);
    }

    public function test_client_can_register_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/user/devices', [
            'name' => 'My Android Phone',
            'type' => 'android',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.attributes.name', 'My Android Phone');
        $response->assertJsonPath('data.attributes.type', 'android');
        $response->assertJsonPath('data.attributes.is_active', true);

        $token = $response->json('data.attributes.token');
        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => $token,
            'is_active' => true,
        ]);
    }

    public function test_register_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/user/devices', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_register_rejects_invalid_type(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/user/devices', [
            'name' => 'My Device',
            'type' => 'invalid-type@',
            'token' => Str::random(32),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_user_can_register_multiple_devices(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        // First registration
        $first = $this->postJson('/api/v1/user/devices', [
            'name' => 'My Android',
            'type' => 'android',
        ]);
        $first->assertStatus(201);

        // Second registration creates a new device instead of updating the first
        $second = $this->postJson('/api/v1/user/devices', [
            'name' => 'My Other Phone',
            'type' => 'galaxy-s22',
        ]);
        $second->assertStatus(201);

        $this->assertEquals(2, DeviceToken::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'name' => 'My Android',
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'name' => 'My Other Phone',
        ]);
        $this->assertNotSame(
            $first->json('data.attributes.token'),
            $second->json('data.attributes.token'),
        );
    }

    public function test_index_lists_all_registered_devices(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $phone = DeviceToken::factory()->forUser($user)->create(['name' => 'My Phone']);
        $tablet = DeviceToken::factory()->forUser($user)->create(['name' => 'My Tablet']);

        $response = $this->getJson('/api/v1/user/devices');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $phone->id);
        $response->assertJsonPath('data.1.id', $tablet->id);
        $response->assertJsonPath('data.0.attributes.name', 'My Phone');
        $response->assertJsonPath('data.1.attributes.name', 'My Tablet');
    }

    public function test_index_returns_empty_list_without_devices(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/user/devices');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_show_returns_registered_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $device = DeviceToken::factory()->forUser($user)->create();

        $response = $this->getJson('/api/v1/user/devices/'.$device->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $device->id);
        $response->assertJsonPath('data.attributes.name', $device->name);
        $response->assertJsonPath('data.attributes.token', $device->token);
    }

    public function test_show_returns_404_for_unknown_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/user/devices/'.Str::uuid());

        $response->assertStatus(404);
    }

    public function test_cannot_show_another_users_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $otherUser = User::factory()->create();
        $device = DeviceToken::factory()->forUser($otherUser)->create();

        $response = $this->getJson('/api/v1/user/devices/'.$device->id);

        $response->assertStatus(403);
    }

    public function test_destroy_removes_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $device = DeviceToken::factory()->forUser($user)->create();

        $response = $this->deleteJson('/api/v1/user/devices/'.$device->id);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Device removed successfully.']);

        $this->assertDatabaseMissing('device_tokens', ['id' => $device->id]);
    }

    public function test_cannot_destroy_another_users_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $otherUser = User::factory()->create();
        $device = DeviceToken::factory()->forUser($otherUser)->create();

        $response = $this->deleteJson('/api/v1/user/devices/'.$device->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('device_tokens', ['id' => $device->id]);
    }
}
