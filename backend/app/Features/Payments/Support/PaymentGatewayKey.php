<?php

namespace App\Features\Payments\Support;

/**
 * Canonical gateway identifiers. These strings are persisted on
 * payment_transactions.gateway and used to resolve driver instances.
 */
final class PaymentGatewayKey
{
    public const STRIPE = 'stripe';

    public const PAYPAL = 'paypal';

    public const APPLE_PAY = 'apple_pay';

    public const GOOGLE_PLAY = 'google_play';

    public const MYFATOORAH = 'myfatoorah';

    public const ZAINCASH = 'zaincash';

    public const FASTPAY = 'fastpay';

    public const QI_CARD = 'qi_card';

    public const FIB = 'fib';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::STRIPE,
            self::PAYPAL,
            self::APPLE_PAY,
            self::GOOGLE_PLAY,
            self::MYFATOORAH,
            self::ZAINCASH,
            self::FASTPAY,
            self::QI_CARD,
            self::FIB,
        ];
    }

    public static function isValid(string $gateway): bool
    {
        return in_array($gateway, self::all(), true);
    }
}
