<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Delivery Polling
    |--------------------------------------------------------------------------
    |
    | After an SmsRequest is broadcast, the API waits up to `timeout_seconds`
    | for the phone to report delivery status via /api/v1/android/status.
    | If the log is still 'pending' when the window elapses, the event is
    | re-dispatched, up to `max_retries` additional times.
    |
    | Total max dispatches = max_retries + 1.
    |
    */

    'polling' => [

        // Seconds to wait for the phone to report back per dispatch.
        'timeout_seconds' => (float) env('SMS_POLL_TIMEOUT_SECONDS', 2),

        // Milliseconds between DB polls within the window.
        'poll_interval_ms' => (int) env('SMS_POLL_INTERVAL_MS', 300),

        // Additional re-dispatches if status stays 'pending'.
        'max_retries' => (int) env('SMS_MAX_RETRIES', 1),

    ],

];
