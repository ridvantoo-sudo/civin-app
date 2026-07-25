<?php

namespace App\Features\Svip\DTOs;

final readonly class UpgradeSvipData
{
    public function __construct(
        public string $svipLevelId,
        public ?array $metadata = null,
    ) {}
}
