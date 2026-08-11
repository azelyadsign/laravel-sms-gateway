<?php

namespace Tests\Feature\Sms;

use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShowSmsTest extends TestCase
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

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}");

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create();

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_view_own_sms(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'phone' => '+40721234567',
            'message' => 'Test message',
            'direction' => 'sent',
            'status' => 'delivered',
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $smsLog->id,
            'phone' => '+40721234567',
            'message' => 'Test message',
            'direction' => 'sent',
            'status' => 'delivered',
        ]);
    }

    public function test_user_cannot_view_others_sms(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('send-sms');
        Passport::actingAs($viewer, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}");

        $response->assertStatus(403);
    }

    public function test_show_returns_correct_status(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'pending',
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/sms/{$smsLog->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'pending']);
    }
}
