<?php

return [
    'mentorship' => [
        'max_mentees_per_mentor' => (int) env('SOCIAL_MENTOR_MAX_MENTEES', 10),
        'max_active_as_mentee' => 1,
        'mentor_reward_coins' => (int) env('SOCIAL_MENTOR_REWARD_COINS', 50),
        'mentee_reward_coins' => (int) env('SOCIAL_MENTEE_REWARD_COINS', 25),
        // Coins awarded to both when a mentorship session is logged.
        'session_mentor_coins' => (int) env('SOCIAL_MENTOR_SESSION_COINS', 20),
        'session_mentee_coins' => (int) env('SOCIAL_MENTEE_SESSION_COINS', 10),
    ],

    'achievements' => [
        // Seeded catalog is loaded once via seeder/migration defaults.
        'auto_claim' => filter_var(env('SOCIAL_ACHIEVEMENT_AUTO_CLAIM', false), FILTER_VALIDATE_BOOL),
    ],

    'check_in' => [
        // Day-indexed coin rewards (1-based streak day). Cycles after the last entry.
        'rewards' => [
            1 => ['coins' => 10, 'diamonds' => 0],
            2 => ['coins' => 15, 'diamonds' => 0],
            3 => ['coins' => 20, 'diamonds' => 0],
            4 => ['coins' => 25, 'diamonds' => 0],
            5 => ['coins' => 30, 'diamonds' => 0],
            6 => ['coins' => 40, 'diamonds' => 0],
            7 => ['coins' => 100, 'diamonds' => 1],
        ],
        'timezone' => env('SOCIAL_CHECKIN_TIMEZONE', 'UTC'),
        'max_streak_display' => 365,
    ],
];
