<?php

namespace App\Features\LiveShopping\DTOs;

final readonly class PinProductData
{
    public function __construct(
        public string $productId,
        public bool $isPinned = true,
        public ?int $flashDealPrice = null,
        public ?string $flashDealEndsAt = null,
        public int $sortOrder = 0,
    ) {}
}
