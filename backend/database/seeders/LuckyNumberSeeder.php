<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberJackpot;
use Illuminate\Database\Seeder;

class LuckyNumberSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();

        $config = LuckyNumberConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Lucky Number Default',
                'description' => 'Default production Lucky Number configuration.',
                'version' => 1,
                'min_bet_coins' => 10,
                'max_bet_coins' => 10000,
                'default_bet_coins' => 50,
                'chip_amounts' => [10, 50, 100, 500, 1000],
                'modes_enabled' => LuckyNumberConfig::MODES,
                'number_ranges' => LuckyNumberConfig::RANGE_PRESETS,
                'mode_ranges' => [
                    LuckyNumberConfig::MODE_SINGLE_NUMBER => ['min' => 1, 'max' => 10],
                    LuckyNumberConfig::MODE_MULTIPLE_NUMBERS => ['min' => 1, 'max' => 20],
                    LuckyNumberConfig::MODE_LUCKY_PICK => ['min' => 1, 'max' => 20],
                    LuckyNumberConfig::MODE_QUICK_BET => ['min' => 1, 'max' => 10],
                    LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS => ['min' => 1, 'max' => 50],
                    LuckyNumberConfig::MODE_DAILY_LUCKY_NUMBER => ['min' => 1, 'max' => 100],
                    LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT => ['min' => 1, 'max' => 50],
                ],
                'reward_multipliers' => [
                    'exact' => 9,
                    'single' => 9,
                    'match_1' => 1.5,
                    'match_2' => 3,
                    'match_3' => 8,
                    'match_4' => 25,
                    'match_5' => 100,
                ],
                'default_min_number' => 1,
                'default_max_number' => 10,
                'max_picks' => 5,
                'draw_count' => 1,
                'rtp_percent' => 96.00,
                'house_edge_percent' => 4.00,
                'max_tickets_per_day' => 500,
                'cooldown_seconds' => 0,
                'progressive_contribution_percent' => 1.00,
                'jackpot_enabled' => true,
                'bonus_enabled' => true,
                'vip_reward_multiplier' => 1.50,
                'bonus_probabilities' => [
                    'lucky_pick_bonus' => 8,
                    'lucky_pick_multiplier' => 2,
                    'streak_reward' => 5,
                    'streak_multiplier' => 2,
                    'daily_bonus' => 3,
                    'daily_multiplier' => 2,
                    'vip_bonus' => 10,
                    'vip_multiplier' => 1.5,
                    'referral_bonus' => 2,
                    'referral_multiplier' => 1.25,
                    'mystery_bonus' => 4,
                    'mystery_multiplier' => 5,
                ],
                'draw_interval_seconds' => 300,
                'status' => LuckyNumberConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'LuckyNumberSeeder'],
            ],
        );

        if ($config->is_default) {
            LuckyNumberConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $jackpots = [
            ['type' => LuckyNumberJackpot::TYPE_GLOBAL, 'name' => 'Global Jackpot', 'pool' => 25000, 'seed' => 10000, 'contrib' => 0.5],
            ['type' => LuckyNumberJackpot::TYPE_PROGRESSIVE, 'name' => 'Progressive Jackpot', 'pool' => 50000, 'seed' => 20000, 'contrib' => 1.0],
            ['type' => LuckyNumberJackpot::TYPE_VIP, 'name' => 'VIP Jackpot', 'pool' => 75000, 'seed' => 30000, 'contrib' => 0.75, 'vip' => true],
            ['type' => LuckyNumberJackpot::TYPE_DAILY, 'name' => 'Daily Jackpot', 'pool' => 5000, 'seed' => 5000, 'contrib' => 0.5, 'daily' => true],
            ['type' => LuckyNumberJackpot::TYPE_ROOM, 'name' => 'Room Jackpot', 'pool' => 2000, 'seed' => 1000, 'contrib' => 0.25, 'room' => 'global'],
        ];

        foreach ($jackpots as $jackpot) {
            $attrs = [
                'config_id' => $config->getKey(),
                'type' => $jackpot['type'],
                'room_id' => $jackpot['room'] ?? null,
                'daily_date' => ($jackpot['daily'] ?? false) ? now()->toDateString() : null,
            ];

            LuckyNumberJackpot::query()->updateOrCreate(
                $attrs,
                [
                    'name' => $jackpot['name'],
                    'pool_coins' => $jackpot['pool'],
                    'seed_coins' => $jackpot['seed'],
                    'min_pool_coins' => $jackpot['seed'],
                    'contribution_percent' => $jackpot['contrib'],
                    'requires_vip' => (bool) ($jackpot['vip'] ?? false),
                    'status' => LuckyNumberJackpot::STATUS_ACTIVE,
                ],
            );
        }
    }
}
