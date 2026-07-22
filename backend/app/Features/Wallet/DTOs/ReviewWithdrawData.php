<?php

namespace App\Features\Wallet\DTOs;

final readonly class ReviewWithdrawData
{
    public function __construct(
        public string $status,
        public ?string $notes = null,
    ) {}
}
