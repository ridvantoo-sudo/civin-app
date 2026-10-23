<?php

namespace App\Features\Ranking\Actions;

use App\Features\Ranking\DTOs\RankingScoreData;
use App\Features\Ranking\Services\RankingService;
use Illuminate\Support\Collection;

final readonly class CalculateRankings
{
    public function __construct(private RankingService $rankings) {}

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function execute(string $type, string $period): Collection
    {
        return $this->rankings->recalculate($type, $period);
    }

    /**
     * @return array<int, string>
     */
    public function forPeriod(string $period): array
    {
        return $this->rankings->recalculatePeriod($period);
    }
}
