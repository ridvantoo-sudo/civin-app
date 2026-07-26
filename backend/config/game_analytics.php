<?php

return [
    /*
    | Redis / queue connection name used for analytics ingestion jobs.
    | Workers should run: php artisan queue:work redis --queue=analytics
    */
    'queue' => env('GAME_ANALYTICS_QUEUE', 'analytics'),

    'queue_connection' => env('GAME_ANALYTICS_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    /*
    | Cache store for realtime counters / dashboard snapshots.
    */
    'cache_store' => env('GAME_ANALYTICS_CACHE_STORE', env('CACHE_STORE', 'database')),

    'cache_ttl_seconds' => (int) env('GAME_ANALYTICS_CACHE_TTL', 60),

    /*
    | Event namespaces ignored by the wildcard collector (framework noise).
    */
    'ignored_event_prefixes' => [
        'Illuminate\\',
        'Laravel\\',
        'Filament\\',
        'Livewire\\',
        'eloquent.',
        'bootstrapped:',
        'composing:',
        'creating:',
        'created:',
        'updating:',
        'updated:',
        'saving:',
        'saved:',
        'deleting:',
        'deleted:',
        'retrieved:',
        'App\\Features\\GameAnalytics\\',
    ],

    /*
    | Privacy: never persist these payload keys on raw events.
    */
    'redacted_payload_keys' => [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'secret',
        'api_key',
        'email',
        'phone',
        'phone_number',
        'firebase_uid',
        'ip',
        'ip_address',
        'device_token',
        'fcm_token',
    ],

    'export' => [
        'disk' => env('GAME_ANALYTICS_EXPORT_DISK', 'local'),
        'path' => env('GAME_ANALYTICS_EXPORT_PATH', 'exports/game-analytics'),
        'max_rows' => (int) env('GAME_ANALYTICS_EXPORT_MAX_ROWS', 50000),
    ],

    'aggregation' => [
        'hourly_lookback_hours' => 48,
        'daily_lookback_days' => 45,
        'monthly_lookback_months' => 18,
        'retention_windows' => [1, 7, 30],
    ],

    'realtime' => [
        'channel' => 'analytics.dashboard',
        'broadcast_throttle_seconds' => 5,
    ],

    'scheduled_reports' => [
        'enabled' => filter_var(env('GAME_ANALYTICS_SCHEDULED_REPORTS', true), FILTER_VALIDATE_BOOL),
        'default_formats' => ['csv', 'xls'],
    ],
];
