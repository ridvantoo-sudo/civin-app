<?php

namespace Database\Factories;

use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkReward;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PkRewardFactory extends Factory
{
    protected $model = PkReward::class;

    public function definition(): array
    {
        return [
            'pk_battle_id' => PkBattle::factory(),
            'winner_id' => User::factory(),
            'reward_type' => PkReward::TYPE_DIAMONDS,
            'amount' => 100,
            'created_at' => now(),
        ];
    }
}
