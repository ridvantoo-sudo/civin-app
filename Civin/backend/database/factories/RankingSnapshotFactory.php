<?php

namespace Database\Factories;

use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Models\RankingSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RankingSnapshot>
 */
class RankingSnapshotFactory extends Factory
{
    protected $model = RankingSnapshot::class;

    public function definition(): array
    {
        return [
            'type' => Ranking::TYPE_HOST_DIAMONDS,
            'period' => Ranking::PERIOD_DAILY,
            'data' => [
                'date' => now()->toDateString(),
                'entries' => [],
            ],
            'created_at' => now(),
        ];
    }
}
