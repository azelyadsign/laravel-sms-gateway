<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\DeviceRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Models\DeviceToken;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    /**
     * List all devices registered to the authenticated user.
     */
    #[QueryParameter(name: 'include', description: 'Include relations: user', required: false, example: 'user')]
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->device()->get();

        return DeviceTokenResource::collection($devices)->toResponse($request);
    }

    /**
     * Register a new device for the authenticated user.
     */
    #[QueryParameter(name: 'name', description: 'Device name', required: true, example: 'My iPhone')]
    #[QueryParameter(name: 'type', description: 'Device type (ios, android, galaxy-s22)', required: true, example: 'android')]
    public function store(DeviceRequest $request): JsonResponse
    {
        $device = $request->user()->device()->create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'token' => Str::random(32), // Generate a random token for the device
            'is_active' => true,
        ]);

        return response()->json(new DeviceTokenResource($device), 201);
    }

    /**
     * Show a specific device of the authenticated user.
     */
    #[QueryParameter(name: 'include', description: 'Include relations: user', required: false, example: 'user')]
    public function show(Request $request, DeviceToken $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json(new DeviceTokenResource($device));
    }

    /**
     * Remove a specific device of the authenticated user.
     */
    public function destroy(Request $request, DeviceToken $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $device->delete();

        return response()->json([
            'message' => 'Device removed successfully.',
        ]);
    }
}
