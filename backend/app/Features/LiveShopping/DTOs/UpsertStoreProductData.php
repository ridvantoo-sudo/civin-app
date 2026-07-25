<?php

namespace App\Features\LiveShopping\DTOs;

final readonly class UpsertStoreProductData
{
    public function __construct(
        public string $name,
        public int $priceCoins,
        public int $stock,
        public ?string $description = null,
        public ?string $imageUrl = null,
        public bool $isOfflineAvailable = true,
        public int $sortOrder = 0,
        public ?array $metadata = null,
    ) {}
}
