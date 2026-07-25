<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stream scheduler
    |--------------------------------------------------------------------------
    */
    'scheduler' => [
        'max_upcoming_per_host' => (int) env('CREATOR_MAX_UPCOMING_SCHEDULES', 20),
        'reminder_minutes' => (int) env('CREATOR_SCHEDULE_REMINDER_MINUTES', 15),
        // Auto-create a LiveRoom shell when a schedule is due (host still starts it).
        'auto_create_room' => filter_var(env('CREATOR_SCHEDULE_AUTO_ROOM', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clips & highlights
    |--------------------------------------------------------------------------
    */
    'clips' => [
        'max_duration_seconds' => (int) env('CREATOR_CLIP_MAX_DURATION', 60),
        'min_duration_seconds' => (int) env('CREATOR_CLIP_MIN_DURATION', 3),
        'max_per_room' => (int) env('CREATOR_CLIP_MAX_PER_ROOM', 50),
        // When true, clip records are created as "processing" (CDN encode stub).
        'async_processing' => filter_var(env('CREATOR_CLIP_ASYNC', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Replay center
    |--------------------------------------------------------------------------
    */
    'replays' => [
        'auto_create_on_live_end' => filter_var(env('CREATOR_REPLAY_AUTO', true), FILTER_VALIDATE_BOOL),
        'min_duration_seconds' => (int) env('CREATOR_REPLAY_MIN_DURATION', 30),
        'retention_days' => (int) env('CREATOR_REPLAY_RETENTION_DAYS', 30),
        // Public CDN / object-storage base for VOD URLs (S3/R2/Cloudflare).
        'cdn_base_url' => rtrim((string) env('CREATOR_REPLAY_CDN_BASE', env('APP_URL', 'http://localhost')), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OBS / RTMP ingest
    |--------------------------------------------------------------------------
    | Hosts get an RTMP URL + stream key for OBS/Streamlabs. When Agora Media
    | Gateway credentials are set, keys are issued against Agora; otherwise a
    | self-hosted / CDN ingest URL from env is used.
    */
    'rtmp' => [
        'enabled' => filter_var(env('CREATOR_RTMP_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ingest_base_url' => rtrim((string) env('CREATOR_RTMP_INGEST_URL', 'rtmp://ingest.civin.local/live'), '/'),
        'key_ttl_hours' => (int) env('CREATOR_RTMP_KEY_TTL_HOURS', 24),
        'agora_media_gateway' => [
            'enabled' => filter_var(env('AGORA_MEDIA_GATEWAY_ENABLED', false), FILTER_VALIDATE_BOOL),
            'customer_id' => env('AGORA_CUSTOMER_ID'),
            'customer_secret' => env('AGORA_CUSTOMER_SECRET'),
            'base_url' => env('AGORA_MEDIA_GATEWAY_URL', 'https://api.agora.io'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Host stream analytics
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        // Snapshot interval for live counters (seconds). Used by the collector command.
        'snapshot_interval_seconds' => (int) env('CREATOR_ANALYTICS_SNAPSHOT_INTERVAL', 60),
    ],
];
