<?php

namespace App\Features\Vip\DTOs;

final readonly class PurchaseVipData
{
    public function __construct(
        public string $vipLevelId,
        public ?array $metadata = null,
    ) {}
}
