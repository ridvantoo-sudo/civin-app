<?php

namespace App\Features\Ai\DTOs;

final readonly class RecommendData
{
    public function __construct(
        public string $context = 'discovery',
        public int $limit = 10,
        public ?array $payload = null,
    ) {}
}
