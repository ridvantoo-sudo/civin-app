<?php

namespace App\Features\Wallet\DTOs;

final readonly class RechargeWalletData
{
    public function __construct(
        public string $packageName,
        public int $coins,
        public int $price,
        public string $currency,
        public string $paymentProvider,
        public string $transactionId,
        public ?array $metadata = null,
    ) {}
}
