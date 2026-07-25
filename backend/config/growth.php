<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Referral rewards
    |--------------------------------------------------------------------------
    | Both sides are rewarded when a new user attaches a referral code.
    */
    'referral' => [
        'code_length' => 8,
        'max_uses_per_code' => (int) env('GROWTH_REFERRAL_MAX_USES', 0), // 0 = unlimited
        'referrer_coins' => (int) env('GROWTH_REFERRAL_REFERRER_COINS', 100),
        'referrer_diamonds' => (int) env('GROWTH_REFERRAL_REFERRER_DIAMONDS', 0),
        'invitee_coins' => (int) env('GROWTH_REFERRAL_INVITEE_COINS', 50),
        'invitee_diamonds' => (int) env('GROWTH_REFERRAL_INVITEE_DIAMONDS', 0),
        // Invitee must attach within N days of registration (0 = no limit).
        'attach_window_days' => (int) env('GROWTH_REFERRAL_ATTACH_WINDOW_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invite rewards (standalone invite links)
    |--------------------------------------------------------------------------
    */
    'invite' => [
        'token_length' => 24,
        'inviter_coins' => (int) env('GROWTH_INVITE_INVITER_COINS', 150),
        'inviter_diamonds' => (int) env('GROWTH_INVITE_INVITER_DIAMONDS', 0),
        'invitee_coins' => (int) env('GROWTH_INVITE_INVITEE_COINS', 75),
        'invitee_diamonds' => (int) env('GROWTH_INVITE_INVITEE_DIAMONDS', 0),
        'expires_days' => (int) env('GROWTH_INVITE_EXPIRES_DAYS', 30),
        'max_pending_per_user' => (int) env('GROWTH_INVITE_MAX_PENDING', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Promo / discount codes
    |--------------------------------------------------------------------------
    */
    'promo' => [
        'default_per_user_limit' => 1,
        'code_max_length' => 32,
    ],

    /*
    |--------------------------------------------------------------------------
    | Push / email campaigns
    |--------------------------------------------------------------------------
    */
    'campaigns' => [
        'batch_size' => (int) env('GROWTH_CAMPAIGN_BATCH', 200),
        'from_name' => env('GROWTH_CAMPAIGN_FROM_NAME', env('MAIL_FROM_NAME', 'Civin')),
        'from_address' => env('GROWTH_CAMPAIGN_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        // FCM HTTP v1. When unset, push deliveries are logged (sandbox/no-op).
        'fcm' => [
            'enabled' => filter_var(env('FCM_ENABLED', false), FILTER_VALIDATE_BOOL),
            'project_id' => env('FCM_PROJECT_ID'),
            'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
            'base_url' => env('FCM_BASE_URL', 'https://fcm.googleapis.com'),
        ],
    ],
];
