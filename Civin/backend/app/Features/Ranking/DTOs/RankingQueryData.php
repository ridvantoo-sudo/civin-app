<?php

namespace App\Features\Ranking\DTOs;

final readonly class RankingQueryData
{
    public function __construct(
        public string $type,
        public string $period,
        public ?string $country = null,
        public int $limit = 50,
    ) {}
}
