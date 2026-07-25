<?php

namespace App\Features\LiveShopping\DTOs;

final readonly class CartItemData
{
    public function __construct(
        public string $productId,
        public int $quantity = 1,
        public ?string $livePinId = null,
        public ?string $roomId = null,
    ) {}
}
