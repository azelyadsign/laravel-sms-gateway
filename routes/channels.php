<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('sms.{deviceType}.{userId}', function ($user, string $deviceType, string $userId) {
    return $user !== null && $user->id === $userId;
});
