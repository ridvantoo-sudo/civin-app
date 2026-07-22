<?php

namespace App\Features\Ranking\DTOs;

final readonly class RankingScoreData
{
    public function __construct(
        public string $userId,
        public int $score,
        public int $rank,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'score' => $this->score,
            'rank' => $this->rank,
        ];
    }
}
