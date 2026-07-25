<?php

namespace App\Features\LiveShopping\DTOs;

final readonly class CreateStoreCouponData
{
    public function __construct(
        public string $code,
        public string $type,
        public int $value,
        public ?int $minOrderCoins = null,
        public ?int $maxDiscountCoins = null,
        public ?int $maxRedemptions = null,
        public int $perUserLimit = 1,
        public ?\DateTimeInterface $startsAt = null,
        public ?\DateTimeInterface $endsAt = null,
        public ?string $description = null,
        public ?array $metadata = null,
    ) {}
}
