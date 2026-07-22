<?php

namespace Database\Factories;

use App\Features\Ranking\Models\Ranking;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ranking>
 */
class RankingFactory extends Factory
{
    protected $model = Ranking::class;

    public function definition(): array
    {
        return [
            'type' => Ranking::TYPE_HOST_DIAMONDS,
            'period' => Ranking::PERIOD_DAILY,
            'user_id' => User::factory(),
            'score' => fake()->numberBetween(1, 10000),
            'rank' => fake()->numberBetween(1, 100),
            'date' => now()->toDateString(),
            'created_at' => now(),
        ];
    }
}
