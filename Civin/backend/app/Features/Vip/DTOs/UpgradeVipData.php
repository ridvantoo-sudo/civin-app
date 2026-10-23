<?php

namespace App\Features\Vip\DTOs;

final readonly class UpgradeVipData
{
    public function __construct(
        public string $vipLevelId,
        public ?array $metadata = null,
    ) {}
}
