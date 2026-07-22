<?php

namespace Database\Factories;

use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Models\PkScore;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PkScoreFactory extends Factory
{
    protected $model = PkScore::class;

    public function definition(): array
    {
        return [
            'pk_battle_id' => PkBattle::factory(),
            'user_id' => User::factory(),
            'score' => 0,
            'gift_coins' => 0,
            'updated_at' => now(),
        ];
    }
}
