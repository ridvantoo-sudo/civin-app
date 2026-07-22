<?php

namespace App\Features\Ranking\Actions;

use App\Features\Ranking\DTOs\RankingQueryData;
use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Services\RankingService;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ListHostRankings
{
    public function __construct(private RankingService $rankings) {}

    public function execute(User $actor, string $period, ?string $country, int $limit): Collection
    {
        return $this->rankings->list($actor, new RankingQueryData(
            type: Ranking::TYPE_HOST_DIAMONDS,
            period: $period,
            country: $country,
            limit: $limit,
        ));
    }
}
