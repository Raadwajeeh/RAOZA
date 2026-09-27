<?php

return [
    'health_token' => env('HEALTH_CHECK_TOKEN'),
    'backup_disk' => env('BACKUP_DISK', 'local'),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'release' => env('APP_RELEASE', 'unknown'),
];
