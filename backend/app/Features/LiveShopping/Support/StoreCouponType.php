<?php

namespace App\Features\LiveShopping\Support;

final class StoreCouponType
{
    public const PERCENT = 'percent';

    public const FIXED_COINS = 'fixed_coins';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PERCENT, self::FIXED_COINS];
    }
}
