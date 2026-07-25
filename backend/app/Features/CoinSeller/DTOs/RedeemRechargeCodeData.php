<?php

namespace App\Features\CoinSeller\DTOs;

final readonly class RedeemRechargeCodeData
{
    public function __construct(
        public string $code,
    ) {}
}
