<?php

namespace Tests\Feature\Android;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastingAuthTest extends TestCase
{
    use RefreshDatabase;

    private DeviceToken $deviceToken;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userId = $user->id;

        $this->deviceToken = DeviceToken::factory()->create([
            'type' => 'android',
            'is_active' => true,
        ]);
    }

    private function channelName(?string $deviceType = null): string
    {
        $type = $deviceType ?? $this->deviceToken->type;

        return 'private-sms.'.$type.'.'.$this->userId;
    }

    public function test_missing_device_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => $this->channelName(),
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Device token required.']);
    }

    public function test_invalid_device_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => $this->channelName(),
        ], [
            'X-Device-Token' => 'invalid-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid or inactive device token.']);
    }

    public function test_valid_device_token_returns_auth_signature(): void
    {
        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => $this->channelName(),
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }

    public function test_auth_signature_format(): void
    {
        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => $this->channelName(),
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(200);

        $auth = $response->json('auth');
        $this->assertStringContainsString(':', $auth);

        [$key, $signature] = explode(':', $auth, 2);
        $this->assertNotEmpty($key);
        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 hex = 64 chars
    }

    public function test_channel_with_nonexistent_user_is_rejected(): void
    {
        $nonexistentId = '00000000-0000-0000-0000-000000000000';

        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-sms.android.'.$nonexistentId,
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel_name']);
    }

    public function test_invalid_channel_name_format_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-sms.'.$this->userId,
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel_name']);
    }

    public function test_user_linked_device_can_subscribe_to_own_channel(): void
    {
        $owner = User::factory()->create();
        $linkedDevice = DeviceToken::factory()->forUser($owner)->create([
            'type' => 'android',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-sms.android.'.$owner->id,
        ], [
            'X-Device-Token' => $linkedDevice->token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }

    public function test_user_linked_device_cannot_subscribe_to_other_users_channel(): void
    {
        $owner = User::factory()->create();
        $linkedDevice = DeviceToken::factory()->forUser($owner)->create([
            'type' => 'android',
            'is_active' => true,
        ]);

        $otherUser = User::factory()->create();

        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-sms.android.'.$otherUser->id,
        ], [
            'X-Device-Token' => $linkedDevice->token,
        ]);

        $response->assertStatus(403);
    }

    public function test_device_cannot_subscribe_to_different_device_type_channel(): void
    {
        $owner = User::factory()->create();
        $linkedDevice = DeviceToken::factory()->forUser($owner)->create([
            'type' => 'android',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-sms.iot.'.$owner->id,
        ], [
            'X-Device-Token' => $linkedDevice->token,
        ]);

        $response->assertStatus(403);
    }

    public function test_iot_device_can_subscribe_to_iot_channel(): void
    {
        $owner = User::factory()->create();
        $iotDevice = DeviceToken::factory()->forUser($owner)->create([
            'type' => 'iot',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-sms.iot.'.$owner->id,
        ], [
            'X-Device-Token' => $iotDevice->token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }
}
