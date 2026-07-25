<?php

return [
    /*
    | Minimum XP user level required to create or manage self-service ads.
    | SVIP members bypass this when allow_svip is true.
    */
    'min_creator_level' => (int) env('ADS_MIN_CREATOR_LEVEL', 10),

    /*
    | Active SVIP membership unlocks ad creation regardless of level.
    */
    'allow_svip' => filter_var(env('ADS_ALLOW_SVIP', true), FILTER_VALIDATE_BOOL),

    /*
    | Max active self-service campaigns a single user may run at once.
    */
    'max_active_campaigns_per_user' => (int) env('ADS_MAX_ACTIVE_PER_USER', 5),

    /*
    | Default number of ads returned per placement serve request.
    */
    'default_serve_limit' => (int) env('ADS_DEFAULT_SERVE_LIMIT', 5),

    'max_serve_limit' => 20,
];
