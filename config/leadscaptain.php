<?php

declare(strict_types=1);

return [
    'base_url' => env(
        'LEADSCAPTAIN_BASE_URL',
        'https://api.leadscaptain.com'
    ),

    'api_key' => env('LEADSCAPTAIN_API_KEY'),

    'timeout' => (int) env('LEADSCAPTAIN_TIMEOUT', 30),

    'retry_times' => (int) env('LEADSCAPTAIN_RETRY_TIMES', 3),

    'concurrency' => (int) env('LEADSCAPTAIN_CONCURRENCY', 10),

    'failure_notification_email' => env(
         'LEADSCAPTAIN_FAILURE_NOTIFICATION_EMAIL'
    ),
];