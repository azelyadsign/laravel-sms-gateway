<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('sms.{userId}', function ($user, string $userId) {
    return $user !== null && $user->id === $userId;
});
