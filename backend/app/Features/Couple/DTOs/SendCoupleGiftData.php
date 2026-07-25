<?php

namespace App\Features\Couple\DTOs;

final readonly class SendCoupleGiftData
{
    public function __construct(
        public string $giftId,
        public int $quantity = 1,
    ) {}
}
