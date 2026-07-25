<?php

namespace App\Features\Couple\DTOs;

final readonly class UpdateCoupleProfileData
{
    public function __construct(
        public ?string $displayName = null,
        public ?string $coverUrl = null,
    ) {}
}
