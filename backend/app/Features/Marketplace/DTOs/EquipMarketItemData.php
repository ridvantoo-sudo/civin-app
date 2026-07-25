<?php

namespace App\Features\Marketplace\DTOs;

final readonly class EquipMarketItemData
{
    public function __construct(
        public string $userMarketItemId,
    ) {}
}
