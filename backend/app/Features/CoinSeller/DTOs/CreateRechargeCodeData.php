<?php

namespace App\Features\CoinSeller\DTOs;

final readonly class CreateRechargeCodeData
{
    public function __construct(
        public int $coins,
        public ?int $expiresInHours = 72,
    ) {}
}
