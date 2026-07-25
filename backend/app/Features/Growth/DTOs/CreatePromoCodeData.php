<?php

namespace App\Features\Growth\DTOs;

final readonly class CreatePromoCodeData
{
    public function __construct(
        public string $code,
        public string $rewardType,
        public int $rewardValue,
        public ?int $maxRedemptions = null,
        public int $perUserLimit = 1,
        public ?string $description = null,
        public ?\DateTimeInterface $startsAt = null,
        public ?\DateTimeInterface $endsAt = null,
        public ?array $metadata = null,
    ) {}
}
