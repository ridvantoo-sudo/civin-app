<?php

return [
    'sticker_packs' => [
        'max_stickers_per_pack' => (int) env('MARKETPLACE_MAX_STICKERS_PER_PACK', 64),
        // Owning a pack unlocks stickers; equip marks the pack as the active picker set.
        'auto_equip_on_purchase' => filter_var(
            env('MARKETPLACE_STICKER_AUTO_EQUIP', false),
            FILTER_VALIDATE_BOOL,
        ),
    ],

    'profile_themes' => [
        'auto_equip_on_purchase' => filter_var(
            env('MARKETPLACE_THEME_AUTO_EQUIP', true),
            FILTER_VALIDATE_BOOL,
        ),
    ],
];
