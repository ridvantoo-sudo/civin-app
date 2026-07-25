<?php

namespace App\Features\Security\Support;

/**
 * The only outcomes the engine may produce. It never mutates gameplay state —
 * it returns one of these decisions and lets ModerationEngine apply it.
 */
final class SecurityAction
{
    public const ALLOW = 'allow';

    public const WARN = 'warn';

    public const THROTTLE = 'throttle';

    public const SUSPEND = 'suspend';

    public const BAN = 'ban';

    public const REVIEW = 'review';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ALLOW,
            self::WARN,
            self::THROTTLE,
            self::SUSPEND,
            self::BAN,
            self::REVIEW,
        ];
    }

    /**
     * Ordered weakest → strongest. Used to pick the winning decision when
     * several rules match the same event.
     */
    public static function weight(string $action): int
    {
        return match ($action) {
            self::ALLOW => 0,
            self::WARN => 10,
            self::THROTTLE => 20,
            self::REVIEW => 30,
            self::SUSPEND => 40,
            self::BAN => 50,
            default => 0,
        };
    }

    public static function strongest(string $left, string $right): string
    {
        return self::weight($right) > self::weight($left) ? $right : $left;
    }

    public static function isEnforcement(string $action): bool
    {
        return in_array($action, [self::THROTTLE, self::SUSPEND, self::BAN], true);
    }

    public static function normalize(?string $action): string
    {
        $candidate = strtolower(trim((string) $action));

        return in_array($candidate, self::all(), true) ? $candidate : self::ALLOW;
    }
}
