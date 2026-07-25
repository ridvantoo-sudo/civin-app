<?php

namespace App\Features\Growth\Support;

final class PromoRewardType
{
    public const COINS = 'coins';

    public const DIAMONDS = 'diamonds';

    public const PERCENT_OFF = 'percent_off';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::COINS, self::DIAMONDS, self::PERCENT_OFF];
    }
}
