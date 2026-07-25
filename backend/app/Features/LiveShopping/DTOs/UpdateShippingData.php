<?php

namespace App\Features\LiveShopping\DTOs;

final readonly class UpdateShippingData
{
    public function __construct(
        public string $status,
        public ?string $trackingNumber = null,
    ) {}
}
