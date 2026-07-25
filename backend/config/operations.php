<?php

return [
    'metrics_token' => env('OPS_METRICS_TOKEN'),
    'readiness_token' => env('OPS_READINESS_TOKEN'),
    'storage_disk' => env('OPS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
    'backup_disk' => env('BACKUP_DISK', 's3'),
    'backup_prefix' => trim(env('BACKUP_PREFIX', 'backups/civin'), '/'),
];
