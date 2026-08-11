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
        $response = $this->getJson('/api/v1/user/device');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/user/device');

        $response->assertStatus(403);
    }

    public function test_client_can_register_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $token = Str::random(64);

        $response = $this->postJson('/api/v1/user/device', [
            'name' => 'My Android Phone',
            'type' => 'android',
            'token' => $token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('device.data.attributes.name', 'My Android Phone');
        $response->assertJsonPath('device.data.attributes.type', 'android');
        $response->assertJsonMissingPath('device.data.attributes.token');

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

        $response = $this->postJson('/api/v1/user/device', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'type', 'token']);
    }

    public function test_register_rejects_invalid_type(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/user/device', [
            'name' => 'My Device',
            'type' => 'invalid-type',
            'token' => Str::random(64),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_register_rejects_token_owned_by_another_user(): void
    {
        $otherUser = User::factory()->create();
        $existingToken = Str::random(64);
        DeviceToken::factory()->forUser($otherUser)->create(['token' => $existingToken]);

        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/user/device', [
            'name' => 'My Device',
            'type' => 'android',
            'token' => $existingToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_register_rejects_gateway_token(): void
    {
        $gatewayToken = Str::random(64);
        DeviceToken::factory()->create(['token' => $gatewayToken]);

        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/user/device', [
            'name' => 'My Device',
            'type' => 'android',
            'token' => $gatewayToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_register_same_token_updates_existing_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $token = Str::random(64);

        // First registration
        $first = $this->postJson('/api/v1/user/device', [
            'name' => 'My Android',
            'type' => 'android',
            'token' => $token,
        ]);
        $first->assertStatus(201);

        // Second registration with same token should update
        $second = $this->postJson('/api/v1/user/device', [
            'name' => 'My Updated Android',
            'type' => 'android',
            'token' => $token,
        ]);
        $second->assertStatus(200);

        $this->assertEquals(1, DeviceToken::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'name' => 'My Updated Android',
        ]);
    }

    public function test_show_returns_registered_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $device = DeviceToken::factory()->forUser($user)->create();

        $response = $this->getJson('/api/v1/user/device');

        $response->assertStatus(200);
        $response->assertJsonPath('device.data.id', $device->id);
        $response->assertJsonPath('device.data.attributes.name', $device->name);
        $response->assertJsonMissingPath('device.data.attributes.token');
    }

    public function test_show_returns_404_without_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/user/device');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'No device registered.']);
    }

    public function test_destroy_removes_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        DeviceToken::factory()->forUser($user)->create();

        $response = $this->deleteJson('/api/v1/user/device');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Device removed successfully.']);

        $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);
    }

    public function test_user_can_only_have_one_device(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        Passport::actingAs($user, [], 'api');

        $tokenA = Str::random(64);
        $tokenB = Str::random(64);

        $this->postJson('/api/v1/user/device', [
            'name' => 'Device A',
            'type' => 'android',
            'token' => $tokenA,
        ]);

        $this->postJson('/api/v1/user/device', [
            'name' => 'Device B',
            'type' => 'android',
            'token' => $tokenB,
        ]);

        $this->assertEquals(1, DeviceToken::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'name' => 'Device B',
            'token' => $tokenB,
        ]);
    }
}
