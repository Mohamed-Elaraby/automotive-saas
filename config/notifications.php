<?php

return [
    'admin' => [
        'transport' => env('ADMIN_NOTIFICATIONS_TRANSPORT', 'sse'),
        'sse_poll_seconds' => (int) env('ADMIN_NOTIFICATIONS_SSE_POLL_SECONDS', 10),
    ],
];
