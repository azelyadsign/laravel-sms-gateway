<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\DeviceRequest;
use App\Http\Resources\DeviceTokenResource;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    /**
     * Show the authenticated user's registered device.
     */
    #[QueryParameter(name: 'include', description: 'Include relations: user', required: false, example: 'user')]
    public function show(Request $request): JsonResponse
    {
        $device = $request->user()->device;

        if (! $device) {
            return response()->json(['message' => 'No device registered.'], 404);
        }

        return response()->json(new DeviceTokenResource($device));
    }

    /**
     * Register or update the authenticated user's device.
     */
    #[QueryParameter(name: 'name', description: 'Device name', required: true, example: 'My iPhone')]
    #[QueryParameter(name: 'type', description: 'Device type (ios, android)', required: true, example: 'android')]
    public function store(DeviceRequest $request): JsonResponse
    {
        $device = $request->user()->device()->updateOrCreate([], [
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'token' => Str::random(32), // Generate a random token for the device
            'is_active' => true,
        ]);

        return response()->json([
            new DeviceTokenResource($device),
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove the authenticated user's device.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->device()?->delete();

        return response()->json([
            'message' => 'Device removed successfully.',
        ]);
    }
}
