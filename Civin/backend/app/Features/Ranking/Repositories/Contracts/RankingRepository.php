<?php

namespace App\Features\Ranking\Repositories\Contracts;

use App\Features\Ranking\DTOs\RankingQueryData;
use App\Features\Ranking\DTOs\RankingScoreData;
use App\Features\Ranking\Models\RankingSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface RankingRepository
{
    /**
     * @param  Collection<int, RankingScoreData>  $scores
     */
    public function replacePeriodRankings(
        string $type,
        string $period,
        CarbonInterface $date,
        Collection $scores,
    ): void;

    public function createSnapshot(string $type, string $period, array $data): RankingSnapshot;

    /**
     * @return Collection<int, \App\Features\Ranking\Models\Ranking>
     */
    public function listStored(RankingQueryData $query, CarbonInterface $date): Collection;

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function aggregateHostDiamonds(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection;

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function aggregateLiveDuration(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection;

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function aggregateTopGifters(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection;

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function aggregatePkWinners(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection;

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function aggregateVoiceHosts(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection;

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function aggregatePopularUsers(?CarbonInterface $from, ?CarbonInterface $to, ?string $country, int $limit): Collection;
}
