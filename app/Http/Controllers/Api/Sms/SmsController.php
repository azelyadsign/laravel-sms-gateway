<?php

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sms\SendSmsRequest;
use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    /**
     * Queue an SMS for delivery via the Android gateway.
     */
    public function send(SendSmsRequest $request): JsonResponse
    {
        $log = SmsLog::create([
            'phone' => $request->validated('phone'),
            'message' => $request->validated('message'),
            'direction' => 'sent',
            'device_type' => $request->validated('device_type'),
            'status' => 'pending',
            'user_id' => $request->user()->id,
        ]);

        SendSmsJob::dispatch($log->id);

        return response()->json([
            'message' => 'SMS queued for delivery.',
            'sms_log_id' => $log->id,
        ], 202);
    }

    /**
     * Show the delivery status of an SMS.
     */
    public function show(Request $request, SmsLog $smsLog): JsonResponse
    {
        if ($smsLog->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'id' => $smsLog->id,
            'phone' => $smsLog->phone,
            'message' => $smsLog->message,
            'direction' => $smsLog->direction,
            'status' => $smsLog->status,
            'external_id' => $smsLog->external_id,
            'raw_response' => $smsLog->raw_response,
            'created_at' => $smsLog->created_at,
            'updated_at' => $smsLog->updated_at,
        ]);
    }

    /**
     * Retry a failed SMS delivery.
     */
    public function retry(Request $request, SmsLog $smsLog): JsonResponse
    {
        if ($smsLog->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($smsLog->status !== 'failed') {
            return response()->json(['message' => 'SMS is not in failed state.'], 422);
        }

        $smsLog->update(['status' => 'pending']);

        SendSmsJob::dispatch($smsLog->id);

        return response()->json([
            'message' => 'SMS retry queued.',
            'sms_log_id' => $smsLog->id,
        ], 202);
    }
}
