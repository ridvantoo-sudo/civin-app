<?php

namespace App\Features\CreatorTools\Support;

final class ScheduleStatus
{
    public const SCHEDULED = 'scheduled';

    public const REMINDED = 'reminded';

    public const STARTED = 'started';

    public const CANCELLED = 'cancelled';

    public const MISSED = 'missed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SCHEDULED, self::REMINDED, self::STARTED, self::CANCELLED, self::MISSED];
    }
}
