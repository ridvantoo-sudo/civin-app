<?php

namespace App\Features\Marketplace\DTOs;

final readonly class PurchaseMarketItemData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $marketItemId,
        public ?array $metadata = null,
    ) {}
}
