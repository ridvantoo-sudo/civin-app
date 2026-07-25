<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class SendFamilyGiftData
{
    public function __construct(
        public string $giftId,
        public int $quantity = 1,
    ) {}
}
