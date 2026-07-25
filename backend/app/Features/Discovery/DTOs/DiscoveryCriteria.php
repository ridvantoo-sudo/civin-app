<?php

namespace App\Features\Discovery\DTOs;

final readonly class DiscoveryCriteria
{
    public function __construct(
        public int $perPage = 20,
        public ?string $country = null,
        public ?int $categoryId = null,
        public ?string $query = null,
        public ?string $type = null,
    ) {}
}
