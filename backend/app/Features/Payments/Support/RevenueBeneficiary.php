<?php

namespace App\Features\Payments\Support;

final class RevenueBeneficiary
{
    public const PLATFORM = 'platform';

    public const MERCHANT = 'merchant';

    public const AGENCY = 'agency';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PLATFORM, self::MERCHANT, self::AGENCY];
    }
}
