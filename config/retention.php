<?php

return [
    // Destructive pruning is disabled until owner/legal retention periods are approved.
    'pruning_enabled' => (bool) env('RETENTION_PRUNING_ENABLED', false),
    'categories' => [
        'analytics_events' => ['days' => env('RETENTION_ANALYTICS_DAYS')],
        'sessions' => ['days' => env('RETENTION_SESSIONS_DAYS')],
        'consent_records' => ['days' => env('RETENTION_CONSENT_DAYS')],
        'failed_jobs' => ['days' => env('RETENTION_FAILED_JOBS_DAYS')],
        'password_reset_tokens' => ['days' => env('RETENTION_PASSWORD_RESET_DAYS')],
    ],
];
