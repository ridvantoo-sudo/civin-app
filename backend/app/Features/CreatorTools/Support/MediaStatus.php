<?php

namespace App\Features\CreatorTools\Support;

final class MediaStatus
{
    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PROCESSING, self::READY, self::FAILED, self::EXPIRED];
    }
}
