<?php

return [
    'cdn' => [
        'base_url' => rtrim((string) env('CDN_BASE_URL', 'https://cdn.civin.app'), '/'),
        'public_base_url' => rtrim((string) env('CDN_PUBLIC_BASE_URL', env('CDN_BASE_URL', 'https://cdn.civin.app')), '/'),
        'cache_ttl_seconds' => (int) env('CDN_CACHE_TTL', 86400),
        'image' => [
            'width' => 1080,
            'quality' => 80,
            'format' => 'webp',
        ],
        'prefixes' => [
            'avatars' => '/avatars',
            'marketplace' => '/marketplace',
            'live' => '/live',
            'stickers' => '/stickers',
        ],
        'edge_map' => [
            'IQ' => 'me-central-1',
            'TR' => 'eu-central-1',
            'DEFAULT' => 'eu-central-1',
        ],
    ],

    'timezone' => [
        'default' => env('APP_TIMEZONE_USER_DEFAULT', 'Asia/Baghdad'),
        'catalog' => [
            'UTC',
            'Asia/Baghdad',
            'Asia/Erbil',
            'Asia/Tehran',
            'Asia/Istanbul',
            'Asia/Riyadh',
            'Asia/Dubai',
            'Europe/London',
            'Europe/Berlin',
            'America/New_York',
        ],
    ],

    'compliance' => [
        // Seeded DB rules take precedence; these are fallback defaults.
        'default_min_age' => 13,
        'default_action' => 'allow',
    ],
];
