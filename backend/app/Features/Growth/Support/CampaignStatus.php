<?php

namespace App\Features\Growth\Support;

final class CampaignStatus
{
    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::SCHEDULED, self::SENDING, self::SENT, self::CANCELLED];
    }
}
