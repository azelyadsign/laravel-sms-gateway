<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BroadcastingAuthController extends Controller
{
    /**
     * Authenticate a device for a private broadcast channel.
     *
     * Generates a Pusher-compatible HMAC-SHA256 signature using the
     * Reverb app secret so the device can subscribe to private channels.
     *
     * Channels follow the format private-sms.{deviceType}.{userId}.
     * A user-linked device may only subscribe to channels matching its
     * own device type and owner. An unlinked device (admin gateway) may
     * subscribe to any channel.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id' => ['required', 'string'],
            'channel_name' => ['required', 'string', 'regex:/^private-sms\.([a-z]+)\.([a-f0-9-]{36})$/'],
        ]);

        preg_match('/^private-sms\.([a-z]+)\.([a-f0-9-]{36})$/', $request->input('channel_name'), $matches);
        $deviceType = $matches[1];
        $userId = $matches[2];

        if (! User::where('id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'channel_name' => ['The specified user does not exist.'],
            ]);
        }

        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('deviceToken');

        // A user-linked device may only subscribe to its owner's channel
        // and must match its own device type.
        if ($deviceToken->user_id !== null) {
            if ($deviceToken->user_id !== $userId) {
                abort(403, 'This device is not linked to the specified user.');
            }

            if ($deviceToken->type !== $deviceType) {
                abort(403, "This device type '{$deviceToken->type}' cannot subscribe to '{$deviceType}' channels.");
            }
        }

        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');

        $signature = hash_hmac(
            'sha256',
            $request->input('socket_id').':'.$request->input('channel_name'),
            $secret
        );

        return response()->json([
            'auth' => $key.':'.$signature,
        ]);
    }
}
