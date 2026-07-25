<?php

namespace App\Features\Global\Support;

final class ComplianceAction
{
    public const ALLOW = 'allow';

    public const DENY = 'deny';

    public const RESTRICT = 'restrict';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ALLOW, self::DENY, self::RESTRICT];
    }
}
