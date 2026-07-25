<?php

namespace App\Features\Payments\DTOs;

/**
 * Normalized representation of a verified inbound webhook.
 */
final readonly class GatewayWebhookResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,             // PaymentStatus::* mapped
        public ?string $eventType = null,
        public ?string $providerEventId = null,
        public ?string $providerReference = null,
        public ?string $providerPaymentId = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public bool $ignored = false,
        public array $raw = [],
    ) {}
}
