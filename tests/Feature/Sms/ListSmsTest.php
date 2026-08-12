<?php

namespace Tests\Feature\Sms;

use App\Models\DeviceToken;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ListSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/sms');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $response = $this->getJson('/api/v1/sms');

        $response->assertStatus(403);
    }

    public function test_user_can_list_own_sms(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $sent = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'delivered',
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/v1/sms');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.id', $sent->id);
        $response->assertJsonPath('data.0.direction', 'sent');
        $response->assertJsonPath('data.0.status', 'delivered');
        $response->assertJsonPath('data.0.device', null);
    }

    public function test_list_does_not_include_other_users_sms(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $otherUser = User::factory()->create();
        SmsLog::factory()->create([
            'direction' => 'sent',
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/sms');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_list_includes_replies_and_device(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $device = DeviceToken::factory()->forUser($user)->create([
            'name' => 'Pixel 9',
            'type' => 'android',
        ]);

        SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'delivered',
            'device_token_id' => $device->id,
            'user_id' => $user->id,
        ]);

        SmsLog::factory()->create([
            'direction' => 'reply',
            'status' => 'received',
            'device_token_id' => $device->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/v1/sms');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.device.name', 'Pixel 9');
        $response->assertJsonPath('data.0.device.type', 'android');
        $response->assertJsonPath('data.1.device.name', 'Pixel 9');
    }

    public function test_list_is_paginated_and_newest_first(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $older = SmsLog::factory()->create([
            'direction' => 'sent',
            'user_id' => $user->id,
            'created_at' => now()->subMinute(),
        ]);

        $newer = SmsLog::factory()->create([
            'direction' => 'sent',
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/sms?per_page=1');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('total', 2);

        $pageTwo = $this->getJson('/api/v1/sms?per_page=1&page=2');

        $pageTwo->assertStatus(200);
        $pageTwo->assertJsonPath('data.0.id', $older->id);
    }
}
