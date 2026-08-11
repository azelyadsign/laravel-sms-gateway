<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeviceToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (! $token) {
            return response()->json(['message' => 'Device token required.'], 401);
        }

        $deviceToken = DeviceToken::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $deviceToken) {
            return response()->json(['message' => 'Invalid or inactive device token.'], 401);
        }

        $request->attributes->set('deviceToken', $deviceToken);

        return $next($request);
    }
}
