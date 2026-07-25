<?php

namespace App\Features\CoinSeller\DTOs;

final readonly class ReviewCoinSellerData
{
    public function __construct(
        public string $status,
        public ?float $commissionRate = null,
        public ?string $notes = null,
    ) {}
}
