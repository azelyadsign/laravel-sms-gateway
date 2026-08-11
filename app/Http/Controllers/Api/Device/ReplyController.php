<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\ReplyRequest;
use App\Models\DeviceToken;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReplyController extends Controller
{
    /**
     * Record an SMS reply or delivery status update from the Android device.
     */
    public function handle(ReplyRequest $request): JsonResponse
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('deviceToken');

        Log::channel('android')->info('Android reply received', [
            'device' => $deviceToken->name,
            'event' => $request->validated('event'),
            'data' => $request->validated('data') ?? null,
            'replied_at' => $request->validated('replied_at') ?? null,
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'received_at' => now()->toIso8601String(),
        ]);

        $log = SmsLog::create([
            'phone' => $request->validated('data.phone'),
            'message' => $request->validated('data.message'),
            'direction' => 'reply',
            'status' => $request->validated('status', 'received'),
            'external_id' => $request->validated('data.external_id'),
            'raw_response' => $request->validated('raw_response'),
            'device_token_id' => $deviceToken->id,
            'user_id' => $deviceToken->user_id,
        ]);

        return response()->json([
            'message' => 'Reply recorded.',
            'sms_log_id' => $log->id,
        ], 201);
    }
}
