<?php

namespace App\Features\CoinSeller\DTOs;

final readonly class DirectSellData
{
    public function __construct(
        public string $buyerId,
        public int $coins,
        public int $priceAmount = 0,
        public string $currency = 'USD',
    ) {}
}
