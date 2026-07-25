<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceConfig;
use Illuminate\Database\Seeder;

class LuckyDiceSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_DICE)->first();

        $config = LuckyDiceConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Lucky Dice Default',
                'description' => 'Default production Lucky Dice configuration.',
                'version' => 1,
                'min_bet_coins' => 50,
                'max_bet_coins' => 10000,
                'default_bet_coins' => 100,
                'chip_amounts' => [50, 100, 200, 500, 1000],
                'max_plays_per_day' => 200,
                'cooldown_seconds' => 0,
                'countdown_seconds' => 5,
                'multiplier_big_small' => 2,
                'multiplier_odd_even' => 2,
                'multiplier_triple' => 30,
                'exact_total_multipliers' => [
                    '3' => 50,
                    '4' => 30,
                    '5' => 18,
                    '6' => 12,
                    '7' => 8,
                    '8' => 6,
                    '9' => 4,
                    '10' => 3,
                    '11' => 3,
                    '12' => 4,
                    '13' => 6,
                    '14' => 8,
                    '15' => 12,
                    '16' => 18,
                    '17' => 30,
                    '18' => 50,
                ],
                'jackpot_enabled' => true,
                'jackpot_coins' => 10000,
                'jackpot_on_triple' => true,
                'modes_enabled' => ['big_small', 'odd_even', 'exact_total', 'triple_match'],
                'status' => LuckyDiceConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'LuckyDiceSeeder'],
            ],
        );

        if ($config->is_default) {
            LuckyDiceConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }
}
