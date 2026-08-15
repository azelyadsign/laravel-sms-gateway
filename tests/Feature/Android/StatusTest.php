<?php

namespace Tests\Feature\Android;

use App\Models\DeviceToken;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTest extends TestCase
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
        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
            'status' => 'delivered',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_device_token_is_rejected(): void
    {
        $inactiveDevice = DeviceToken::factory()->create([
            'type' => 'android',
            'is_active' => false,
        ]);

        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $inactiveDevice->token,
        ]);

        $response->assertStatus(401);
    }

    public function test_active_device_token_can_update_status(): void
    {
        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Status updated.',
            'sms_log_id' => $smsLog->id,
        ]);
    }

    public function test_status_update_persists_changes(): void
    {
        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
            'status' => 'delivered',
            'raw_response' => '{"code":0,"message":"OK"}',
            'external_id' => 'ext-12345',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $smsLog->id,
            'status' => 'delivered',
            'raw_response' => '{"code":0,"message":"OK"}',
            'external_id' => 'ext-12345',
            'device_token_id' => $this->deviceToken->id,
        ]);
    }

    public function test_status_update_sets_device_token_id(): void
    {
        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
            'status' => 'sent',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $smsLog->id,
            'status' => 'sent',
            'device_token_id' => $this->deviceToken->id,
        ]);
    }

    public function test_status_update_with_user_linked_device(): void
    {
        $owner = User::factory()->create();
        $linkedDevice = DeviceToken::factory()->forUser($owner)->create([
            'type' => 'android',
            'is_active' => true,
        ]);

        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $linkedDevice->token,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $smsLog->id,
            'status' => 'delivered',
            'device_token_id' => $linkedDevice->id,
        ]);
    }

    public function test_sms_log_id_is_required(): void
    {
        $response = $this->postJson('/api/v1/device/status', [
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sms_log_id']);
    }

    public function test_status_is_required(): void
    {
        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/device/status', [
            'sms_log_id' => $smsLog->id,
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_sms_log_id_must_exist(): void
    {
        $response = $this->postJson('/api/v1/device/status', [
            'sms_log_id' => 'non-existent-id',
            'status' => 'delivered',
        ], [
            'X-Device-Token' => $this->deviceToken->token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sms_log_id']);
    }
}
