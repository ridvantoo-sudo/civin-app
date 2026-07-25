<?php

namespace App\Features\Payments\DTOs;

final readonly class CreatePaymentIntentData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $gateway,
        public string $payableType,
        public string $payableId,
        public ?string $currency = null,
        public ?string $returnUrl = null,
        public ?string $token = null,       // Apple/Google receipt or client token
        public ?string $redirectUrl = null, // optional gateway redirect override
        public ?array $metadata = null,
    ) {}
}
