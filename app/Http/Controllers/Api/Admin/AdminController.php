<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class AdminController extends Controller
{
    /**
     * List all users with their roles.
     */
    public function index(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(User::class)
            ->allowedSorts('name', 'email', 'created_at')
            ->paginate($request->input('per_page', 15));

        return UserResource::collection($users)
            ->additional([
                'meta' => [
                    'total' => $users->total(),
                ],
            ])
            ->response();
    }

    /**
     * Approve a user by assigning the Client role.
     */
    public function approve(User $user): JsonResponse
    {
        if ($user->hasRole('Client')) {
            return response()->json([
                'message' => 'User is already approved.',
            ], 409);
        }

        $user->assignRole('Client');

        return response()->json([
            'message' => 'User approved successfully.',
            'user' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Revoke approval by removing all roles from a user.
     */
    public function revoke(User $user): JsonResponse
    {
        if (! $user->hasAnyRole(['Client', 'AppClient'])) {
            return response()->json([
                'message' => 'User has no roles to revoke.',
            ], 409);
        }

        $user->syncRoles([]);

        return response()->json([
            'message' => 'User roles revoked successfully.',
            'user' => new UserResource($user->load('roles')),
        ]);
    }
}
