<?php

namespace Tests\Feature\Sms;

use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RetrySmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $smsLog = SmsLog::factory()->create(['status' => 'failed']);

        $response = $this->postJson("/api/v1/sms/{$smsLog->id}/retry");

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create(['status' => 'failed']);

        $response = $this->postJson("/api/v1/sms/{$smsLog->id}/retry");

        $response->assertStatus(403);
    }

    public function test_user_cannot_retry_others_sms(): void
    {
        $owner = User::factory()->create();
        $retryUser = User::factory()->create();
        $retryUser->givePermissionTo('send-sms');
        Passport::actingAs($retryUser, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'status' => 'failed',
            'user_id' => $owner->id,
        ]);

        $response = $this->postJson("/api/v1/sms/{$smsLog->id}/retry");

        $response->assertStatus(403);
    }

    public function test_can_retry_failed_sms(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'failed',
            'user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/sms/{$smsLog->id}/retry");

        $response->assertStatus(202);
        $response->assertJson([
            'message' => 'SMS retry queued.',
            'sms_log_id' => $smsLog->id,
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'id' => $smsLog->id,
            'status' => 'pending',
        ]);

        Bus::assertDispatched(SendSmsJob::class);
    }

    public function test_cannot_retry_non_failed_sms(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $smsLog = SmsLog::factory()->create([
            'direction' => 'sent',
            'status' => 'delivered',
            'user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/sms/{$smsLog->id}/retry");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'SMS is not in failed state.']);

        Bus::assertNotDispatched(SendSmsJob::class);
    }
}
