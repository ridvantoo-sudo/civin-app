<?php

namespace App\Features\Wallet\DTOs;

final readonly class SubmitOfflineRechargeData
{
    public function __construct(
        public int $coins,
        public int $amount,
        public string $currency,
        public string $referenceNumber,
        public string $receiptPath,
        public ?array $metadata = null,
    ) {}
}
