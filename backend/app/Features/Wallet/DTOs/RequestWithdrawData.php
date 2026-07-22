<?php

namespace App\Features\Wallet\DTOs;

final readonly class RequestWithdrawData
{
    public function __construct(
        public int $diamonds,
        public int $amount,
        public ?array $metadata = null,
    ) {}
}
