<?php

namespace App\Features\Agency\DTOs;

final readonly class ApplyAgencyData
{
    public function __construct(
        public ?string $message = null,
    ) {}
}
