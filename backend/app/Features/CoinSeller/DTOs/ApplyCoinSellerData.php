<?php

namespace App\Features\CoinSeller\DTOs;

final readonly class ApplyCoinSellerData
{
    public function __construct(
        public string $displayName,
        public ?string $contactInfo = null,
        public ?string $applicationMessage = null,
    ) {}
}
