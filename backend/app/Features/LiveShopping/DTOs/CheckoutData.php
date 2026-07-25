<?php

namespace App\Features\LiveShopping\DTOs;

final readonly class CheckoutData
{
    /**
     * @param  list<array{product_id: string, quantity: int, live_pin_id?: string|null}>|null  $items
     */
    public function __construct(
        public string $paymentMethod,
        public ?string $roomId = null,
        public ?string $shippingName = null,
        public ?string $shippingPhone = null,
        public ?string $shippingAddressLine = null,
        public ?string $shippingCity = null,
        public ?string $shippingCountry = null,
        public ?string $shippingPostalCode = null,
        public ?string $paymentProvider = null,
        public ?string $paymentTransactionId = null,
        public ?array $items = null,
        public bool $clearCart = true,
        public ?array $metadata = null,
        public ?string $couponCode = null,
    ) {}
}
