<?php

namespace Tests\Feature\Sms;

use App\Jobs\SendSmsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SendSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello',
        ]);

        $response->assertStatus(403);
    }

    public function test_send_sms_with_valid_token_returns_202(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello from test',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'sms_log_id',
        ]);
    }

    public function test_send_sms_validates_phone_required(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'message' => 'Missing phone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_send_sms_validates_message_required(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_send_sms_creates_sms_log_record(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello from test',
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'phone' => '+40721234567',
            'message' => 'Hello from test',
            'direction' => 'sent',
            'status' => 'pending',
            'user_id' => $user->id,
        ]);
    }

    public function test_send_sms_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello from test',
        ]);

        Bus::assertDispatched(SendSmsJob::class);
    }

    public function test_send_sms_with_valid_device_type(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello IoT device',
            'device_type' => 'iot',
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('sms_logs', [
            'phone' => '+40721234567',
            'message' => 'Hello IoT device',
            'device_type' => 'iot',
            'user_id' => $user->id,
        ]);
    }

    public function test_send_sms_rejects_invalid_device_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('send-sms');
        Passport::actingAs($user, [], 'api');

        $response = $this->postJson('/api/v1/sms/send', [
            'phone' => '+40721234567',
            'message' => 'Hello',
            'device_type' => 'invalid-type@',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['device_type']);
    }
}
