<?php

namespace App\Features\Wallet\DTOs;

final readonly class RechargeWalletData
{
    public function __construct(
        public string $packageId,
        public string $paymentProvider,
        public string $transactionId,
        public ?string $receipt = null,
        public ?array $metadata = null,
    ) {}
}
