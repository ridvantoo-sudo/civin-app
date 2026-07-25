<?php

namespace App\Features\Payments\Support;

final class PaymentStatus
{
    public const PENDING = 'pending';

    public const REQUIRES_ACTION = 'requires_action';

    public const PROCESSING = 'processing';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const PARTIALLY_REFUNDED = 'partially_refunded';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::REQUIRES_ACTION,
            self::PROCESSING,
            self::SUCCEEDED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
        ];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [
            self::SUCCEEDED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
        ], true);
    }
}
