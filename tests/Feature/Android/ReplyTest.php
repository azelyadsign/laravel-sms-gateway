<?php

namespace Tests\Feature\Android;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyTest extends TestCase
{
    use RefreshDatabase;

    private DeviceToken $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceToken = DeviceToken::factory()->create([
            'type' => 'android',
            'is_active' => true,
        ]);
    }

    public function test_missing_device_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/device/reply', [
            'event' => 'sms.reply',
            'data' => [
                'phone' => '+40721234567',
                'message' => 'Reply text',
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_active_device_token_can_submit_reply(): void
    {
        $response = $this->postJson('/api/v1/device/reply', [
            'event' => 'sms.reply',
            'data' => [
                'phone' => '+40721234567',
                'message' => 'Reply from phone',
            ],
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'sms_log_id',
        ]);
    }

    public function test_reply_validates_data_phone_required(): void
    {
        $response = $this->postJson('/api/v1/device/reply', [
            'event' => 'sms.reply',
            'data' => [
                'message' => 'Reply without phone',
            ],
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.phone']);
    }

    public function test_reply_creates_sms_log_record(): void
    {
        $this->postJson('/api/v1/device/reply', [
            'event' => 'sms.reply',
            'data' => [
                'phone' => '+40721234567',
                'message' => 'Reply from phone',
            ],
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'phone' => '+40721234567',
            'message' => 'Reply from phone',
            'direction' => 'reply',
            'status' => 'delivered',
            'device_token_id' => $this->deviceToken->id,
        ]);
    }

    public function test_inactive_device_token_is_rejected(): void
    {
        $inactiveDevice = DeviceToken::factory()->create([
            'type' => 'android',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/device/reply', [
            'event' => 'sms.reply',
            'data' => [
                'phone' => '+40721234567',
                'message' => 'Reply',
            ],
        ], [
            'X-Device-Token' => $inactiveDevice->token,
        ]);

        $response->assertStatus(401);
    }

    public function test_reply_from_user_linked_device_sets_user_id(): void
    {
        $owner = User::factory()->create();
        $linkedDevice = DeviceToken::factory()->forUser($owner)->create([
            'type' => 'android',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/device/reply', [
            'event' => 'sms.reply',
            'data' => [
                'phone' => '+40721234567',
                'message' => 'Reply from linked device',
            ],
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $linkedDevice->token,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'phone' => '+40721234567',
            'message' => 'Reply from linked device',
            'direction' => 'reply',
            'status' => 'delivered',
            'device_token_id' => $linkedDevice->id,
            'user_id' => $owner->id,
        ]);
    }
}
