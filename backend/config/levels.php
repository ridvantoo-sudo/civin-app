<?php

return [
    'max_level' => 100,
    'min_level' => 1,

    /*
    | Incremental XP required to advance from level L to L+1:
    | base + (L - 1) * step
    | Total XP to reach level 100 ≈ 252,450 with defaults.
    */
    'xp_base' => 100,
    'xp_step' => 50,

    'sources' => [
        'live' => [
            'xp_per_minute' => 10,
            'daily_cap' => 2000,
            'min_seconds' => 60,
        ],
        'watching' => [
            'xp_per_minute' => 2,
            'daily_cap' => 500,
            'min_seconds' => 60,
        ],
        'gift_send' => [
            'xp_per_coin' => 1,
            'daily_cap' => 5000,
        ],
        'gift_receive' => [
            'xp_per_coin' => 0.5,
            'daily_cap' => 5000,
        ],
        'login' => [
            'xp' => 50,
        ],
    ],
];
