<?php

namespace App\Features\Payments\Gateways;

use App\Features\Payments\Contracts\PaymentGateway;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Support\PaymentGatewayKey;

final class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        PaymentGatewayKey::STRIPE => StripeGateway::class,
        PaymentGatewayKey::PAYPAL => PayPalGateway::class,
        PaymentGatewayKey::APPLE_PAY => ApplePayGateway::class,
        PaymentGatewayKey::GOOGLE_PLAY => GooglePlayGateway::class,
        PaymentGatewayKey::MYFATOORAH => MyFatoorahGateway::class,
        PaymentGatewayKey::ZAINCASH => ZainCashGateway::class,
        PaymentGatewayKey::FASTPAY => FastPayGateway::class,
        PaymentGatewayKey::QI_CARD => QiCardGateway::class,
        PaymentGatewayKey::FIB => FibGateway::class,
    ];

    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function driver(string $gateway): PaymentGateway
    {
        if (! isset(self::DRIVERS[$gateway])) {
            throw new PaymentGatewayException("Unknown payment gateway [{$gateway}].", 'unknown_gateway');
        }

        return $this->resolved[$gateway] ??= new (self::DRIVERS[$gateway])(
            (array) config("payments.gateways.{$gateway}", []),
        );
    }

    /**
     * Resolve a driver only if it is enabled, otherwise throw.
     */
    public function enabledDriver(string $gateway): PaymentGateway
    {
        $driver = $this->driver($gateway);

        if (! $driver->isEnabled()) {
            throw new PaymentGatewayException("The [{$gateway}] gateway is not enabled.", 'gateway_disabled');
        }

        return $driver;
    }

    /**
     * @return list<string> keys of all enabled gateways
     */
    public function enabledKeys(): array
    {
        return array_values(array_filter(
            PaymentGatewayKey::all(),
            fn (string $key): bool => $this->driver($key)->isEnabled(),
        ));
    }
}
