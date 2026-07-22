<?php

namespace App\Features\Agency\DTOs;

final readonly class ReviewApplicationData
{
    public function __construct(
        public string $userId,
    ) {}
}
