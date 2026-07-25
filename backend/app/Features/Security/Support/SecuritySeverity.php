<?php

namespace App\Features\Security\Support;

final class SecuritySeverity
{
    public const INFO = 'info';

    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    public const CRITICAL = 'critical';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::INFO, self::LOW, self::MEDIUM, self::HIGH, self::CRITICAL];
    }

    public static function weight(string $severity): int
    {
        return match ($severity) {
            self::INFO => 1,
            self::LOW => 2,
            self::MEDIUM => 3,
            self::HIGH => 4,
            self::CRITICAL => 5,
            default => 1,
        };
    }

    /** Multiplier applied to a signal's confidence when scoring risk. */
    public static function multiplier(string $severity): float
    {
        return match ($severity) {
            self::INFO => 0.10,
            self::LOW => 0.25,
            self::MEDIUM => 0.50,
            self::HIGH => 0.80,
            self::CRITICAL => 1.00,
            default => 0.10,
        };
    }

    public static function normalize(?string $severity): string
    {
        $candidate = strtolower(trim((string) $severity));

        return in_array($candidate, self::all(), true) ? $candidate : self::LOW;
    }
}
