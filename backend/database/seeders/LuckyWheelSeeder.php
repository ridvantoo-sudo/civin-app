<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelConfig;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSector;
use Illuminate\Database\Seeder;

class LuckyWheelSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->first();

        $config = LuckyWheelConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Lucky Wheel Default',
                'description' => 'Default production Lucky Wheel configuration.',
                'version' => 1,
                'spin_cost_coins' => 100,
                'daily_free_spins' => 0,
                'max_spins_per_day' => 100,
                'cooldown_seconds' => 0,
                'jackpot_enabled' => true,
                'jackpot_coins' => 5000,
                'status' => LuckyWheelConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'LuckyWheelSeeder'],
            ],
        );

        if ($config->is_default) {
            LuckyWheelConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $sectors = [
            [
                'position' => 0,
                'label' => 'Try again',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_NONE,
                'reward_amount' => 0,
                'weight' => 30,
                'color' => '#6B7280',
                'is_jackpot' => false,
            ],
            [
                'position' => 1,
                'label' => '50 coins',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_COINS,
                'reward_amount' => 50,
                'weight' => 25,
                'color' => '#F59E0B',
                'is_jackpot' => false,
            ],
            [
                'position' => 2,
                'label' => '100 coins',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_COINS,
                'reward_amount' => 100,
                'weight' => 20,
                'color' => '#10B981',
                'is_jackpot' => false,
            ],
            [
                'position' => 3,
                'label' => '200 coins',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_COINS,
                'reward_amount' => 200,
                'weight' => 12,
                'color' => '#3B82F6',
                'is_jackpot' => false,
            ],
            [
                'position' => 4,
                'label' => '5 diamonds',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_DIAMONDS,
                'reward_amount' => 5,
                'weight' => 8,
                'color' => '#8B5CF6',
                'is_jackpot' => false,
            ],
            [
                'position' => 5,
                'label' => 'Mystery gift',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_GIFT,
                'reward_amount' => 1,
                'reward_reference' => 'mystery_gift',
                'weight' => 4,
                'color' => '#EC4899',
                'is_jackpot' => false,
                'requires_fulfillment' => true,
            ],
            [
                'position' => 6,
                'label' => 'Jackpot',
                'reward_type' => LuckyWheelSector::REWARD_TYPE_COINS,
                'reward_amount' => 5000,
                'weight' => 1,
                'color' => '#EF4444',
                'is_jackpot' => true,
            ],
        ];

        foreach ($sectors as $sector) {
            LuckyWheelSector::query()->updateOrCreate(
                [
                    'config_id' => $config->getKey(),
                    'position' => $sector['position'],
                ],
                array_merge($sector, [
                    'status' => LuckyWheelSector::STATUS_ACTIVE,
                    'requires_fulfillment' => (bool) ($sector['requires_fulfillment'] ?? false),
                    'reward_reference' => $sector['reward_reference'] ?? null,
                    'icon' => null,
                    'metadata' => null,
                ]),
            );
        }
    }
}
