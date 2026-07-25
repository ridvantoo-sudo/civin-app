<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class ContributeWalletData
{
    public function __construct(
        public int $amount,
        public string $currency = 'coins',
        public ?string $note = null,
    ) {}
}
