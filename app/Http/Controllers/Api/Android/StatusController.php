<?php

namespace App\Http\Controllers\Api\Android;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Android\StatusRequest;
use App\Models\DeviceToken;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StatusController extends Controller
{
    /**
     * Update the delivery status of a sent SMS.
     */
    public function update(StatusRequest $request): JsonResponse
    {

        Log::channel('android')->info('Android SMS Status Update', [
            'body' => $request->all(),
        ]);

        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('deviceToken');

        $smsLog = SmsLog::findOrFail($request->validated('sms_log_id'));

        $smsLog->update([
            'status' => $request->validated('status'),
            'raw_response' => $request->validated('raw_response'),
            'external_id' => $request->validated('external_id', $smsLog->external_id),
            'device_token_id' => $deviceToken->id,
        ]);

        return response()->json([
            'message' => 'Status updated.',
            'sms_log_id' => $smsLog->id,
        ]);
    }
}
