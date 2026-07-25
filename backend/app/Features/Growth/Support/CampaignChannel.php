<?php

namespace App\Features\Growth\Support;

final class CampaignChannel
{
    public const PUSH = 'push';

    public const EMAIL = 'email';

    public const BOTH = 'both';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PUSH, self::EMAIL, self::BOTH];
    }

    /** @return list<string> */
    public static function deliveryChannels(string $channel): array
    {
        return match ($channel) {
            self::PUSH => [self::PUSH],
            self::EMAIL => [self::EMAIL],
            self::BOTH => [self::PUSH, self::EMAIL],
            default => [],
        };
    }
}
