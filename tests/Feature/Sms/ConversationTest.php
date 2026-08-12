<?php

namespace Tests\Feature\Sms;

use App\Models\DeviceToken;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $smsLog = SmsLog::factory()->create();

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}/conversation");

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create();

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}/conversation");

        $response->assertStatus(403);
    }

    public function test_user_can_view_conversation_for_own_sms(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $sent = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'delivered',
            'user_id' => $user->id,
        ]);

        $reply = SmsLog::factory()->create([
            'direction' => 'reply',
            'status' => 'received',
            'external_id' => $sent->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$sent->id}/conversation");

        $response->assertStatus(200);
        $response->assertJsonPath('sms.id', $sent->id);
        $response->assertJsonPath('sms.direction', 'sent');
        $response->assertJsonCount(1, 'replies');
        $response->assertJsonPath('replies.0.id', $reply->id);
        $response->assertJsonPath('replies.0.direction', 'reply');
    }

    public function test_conversation_only_includes_replies_to_that_sms(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $sent = SmsLog::factory()->create([
            'direction' => 'sent',
            'user_id' => $user->id,
        ]);

        $otherSent = SmsLog::factory()->create([
            'direction' => 'sent',
            'user_id' => $user->id,
        ]);

        SmsLog::factory()->create([
            'direction' => 'reply',
            'external_id' => $otherSent->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$sent->id}/conversation");

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'replies');
    }

    public function test_conversation_includes_devices(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $device = DeviceToken::factory()->forUser($user)->create([
            'name' => 'Pixel 9',
            'type' => 'android',
        ]);

        $sent = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'delivered',
            'device_token_id' => $device->id,
            'user_id' => $user->id,
        ]);

        SmsLog::factory()->create([
            'direction' => 'reply',
            'status' => 'received',
            'external_id' => $sent->id,
            'device_token_id' => $device->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$sent->id}/conversation");

        $response->assertStatus(200);
        $response->assertJsonPath('sms.device.name', 'Pixel 9');
        $response->assertJsonPath('replies.0.device.name', 'Pixel 9');
    }

    public function test_user_cannot_view_conversation_for_others_sms(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('send-sms');
        Passport::actingAs($viewer, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}/conversation");

        $response->assertStatus(403);
    }

    public function test_conversation_for_missing_sms_returns_404(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/sms/00000000-0000-0000-0000-000000000000/conversation');

        $response->assertStatus(404);
    }
}
