<?php

namespace App\Features\Payments\DTOs;

/**
 * Normalized result returned by a gateway when a payment intent/session is
 * created. Different gateways populate different fields (redirect vs. client
 * confirmation vs. immediate capture), so most are nullable.
 */
final readonly class GatewayIntent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $providerPaymentId = null,
        public ?string $clientSecret = null,
        public ?string $checkoutUrl = null,
        public array $raw = [],
    ) {}
}
