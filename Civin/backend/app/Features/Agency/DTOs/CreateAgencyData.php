<?php

namespace App\Features\Agency\DTOs;

final readonly class CreateAgencyData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $logo = null,
        public float $commissionRate = 10.0,
    ) {}
}
