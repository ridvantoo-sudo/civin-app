<?php

namespace App\Features\CoinSeller\DTOs;

final readonly class AllocateInventoryData
{
    public function __construct(
        public int $coins,
        public ?string $notes = null,
    ) {}
}
