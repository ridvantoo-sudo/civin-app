<?php

return [
    /*
    | Coin packs the API will credit. Clients choose a package id. The coin
    | amount always comes from this list, never from the request body.
    */
    'recharge_packages' => [
        [
            'id' => 'starter',
            'name' => 'Starter Pack',
            'coins' => 100,
            'bonus_coins' => 0,
            'price' => 99,
            'currency' => 'USD',
            'badge' => 'Popular',
        ],
        [
            'id' => 'plus',
            'name' => 'Plus Pack',
            'coins' => 500,
            'bonus_coins' => 25,
            'price' => 449,
            'currency' => 'USD',
            'badge' => null,
        ],
        [
            'id' => 'mega',
            'name' => 'Mega Pack',
            'coins' => 1200,
            'bonus_coins' => 100,
            'price' => 999,
            'currency' => 'USD',
            'badge' => 'Best value',
        ],
        [
            'id' => 'ultra',
            'name' => 'Ultra Pack',
            'coins' => 3000,
            'bonus_coins' => 400,
            'price' => 2299,
            'currency' => 'USD',
            'badge' => null,
        ],
    ],
];
