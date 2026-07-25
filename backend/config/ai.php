<?php

return [
    'driver' => env('AI_DRIVER', 'fake'),

    'fake' => [
        // Deterministic seed for recommend scores.
        'recommend_seed' => (int) env('AI_FAKE_RECOMMEND_SEED', 42),
        // Words that trigger flag/block in fake text moderation.
        'flag_words' => ['spam', 'scam'],
        'block_words' => ['terror', 'bomb'],
        'translate_prefix' => '[translated]',
    ],

    'rate_limits' => [
        'moderate' => 60,
        'translate' => 60,
        'recommend' => 30,
        'highlights' => 20,
    ],
];
