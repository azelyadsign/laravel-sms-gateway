<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Device\BroadcastingAuthController;
use App\Http\Controllers\Api\Device\ReplyController;
use App\Http\Controllers\Api\Device\StatusController;
use App\Http\Controllers\Api\Auth\AppAuthController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Sms\SmsController;
use App\Http\Controllers\Api\User\DeviceController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes (no auth required)
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes (auth required)
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return new UserResource($request->user());
        });

        // User device registration (requires send-sms permission: Client, AppClient, Admin)
        Route::middleware('permission:send-sms')->group(function () {
            Route::get('/user/device', [DeviceController::class, 'show']);
            Route::post('/user/device', [DeviceController::class, 'store']);
            Route::delete('/user/device', [DeviceController::class, 'destroy']);
        });
    });

    Route::prefix('app')->group(function () {
        Route::post('/register', [AppAuthController::class, 'register']);
        Route::post('/login', [AppAuthController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AppAuthController::class, 'logout']);
        });
    });

    // SMS (requires auth + send-sms permission)
    Route::middleware(['auth:api', 'permission:send-sms', 'throttle:30,1'])->group(function () {
        Route::post('/sms/send', [SmsController::class, 'send']);
        Route::get('/sms/{smsLog}', [SmsController::class, 'show']);
        Route::post('/sms/{smsLog}/retry', [SmsController::class, 'retry']);
    });

    // Admin routes
    Route::prefix('admin')->middleware(['auth:api', 'role:Admin'])->group(function () {
        Route::get('/users', [AdminController::class, 'index']);
        Route::patch('/users/{user}/approve', [AdminController::class, 'approve']);
        Route::patch('/users/{user}/revoke', [AdminController::class, 'revoke']);
    });

    // Android Device Endpoints (static device token)
    Route::prefix('device')->middleware('device-token')->group(function () {
        Route::post('/broadcasting/auth', [BroadcastingAuthController::class, 'authenticate']);
        Route::post('/reply', [ReplyController::class, 'handle']);
        Route::post('/status', [StatusController::class, 'update']);
    });
});
