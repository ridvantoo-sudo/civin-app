<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineJackpot;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachinePayline;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSymbol;
use Illuminate\Database\Seeder;

class SlotMachineSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_SLOT_MACHINE)->first();

        $config = SlotMachineConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Slot Machine Default',
                'description' => 'Default production Slot Machine configuration.',
                'version' => 1,
                'min_bet_coins' => 10,
                'max_bet_coins' => 10000,
                'default_bet_coins' => 50,
                'chip_amounts' => [10, 50, 100, 500, 1000],
                'modes_enabled' => SlotMachineConfig::MODES,
                'rtp_percent' => 96.00,
                'house_edge_percent' => 4.00,
                'classic_columns' => 3,
                'classic_rows' => 3,
                'premium_columns' => 5,
                'premium_rows' => 3,
                'max_spins_per_day' => 500,
                'cooldown_seconds' => 0,
                'daily_free_spins' => 3,
                'max_auto_spins' => 50,
                'max_spin_count' => 10,
                'free_spin_award_on_scatters' => 3,
                'free_spins_granted' => 10,
                'progressive_contribution_percent' => 1.00,
                'jackpot_enabled' => true,
                'bonus_enabled' => true,
                'bonus_trigger_symbols' => 3,
                'vip_reward_multiplier' => 1.25,
                'bonus_probabilities' => [
                    'mystery_reward' => 5,
                    'mystery_multiplier' => 5,
                    'combo_reward' => 10,
                    'combo_multiplier' => 3,
                    'daily_reward' => 2,
                    'daily_multiplier' => 2,
                ],
                'status' => SlotMachineConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'SlotMachineSeeder'],
            ],
        );

        if ($config->is_default) {
            SlotMachineConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $symbols = [
            ['code' => 'cherry', 'label' => 'Cherry', 'weight' => 28, 'payout_3' => 2, 'payout_4' => 5, 'payout_5' => 15, 'color' => '#EF4444'],
            ['code' => 'coin', 'label' => 'Coin', 'weight' => 24, 'payout_3' => 3, 'payout_4' => 8, 'payout_5' => 20, 'color' => '#F59E0B'],
            ['code' => 'star', 'label' => 'Star', 'weight' => 18, 'payout_3' => 4, 'payout_4' => 10, 'payout_5' => 25, 'color' => '#EAB308'],
            ['code' => 'gold_bar', 'label' => 'Gold Bar', 'weight' => 14, 'payout_3' => 6, 'payout_4' => 15, 'payout_5' => 40, 'color' => '#D97706'],
            ['code' => 'diamond', 'label' => 'Diamond', 'weight' => 10, 'payout_3' => 10, 'payout_4' => 25, 'payout_5' => 60, 'color' => '#38BDF8'],
            ['code' => 'treasure_chest', 'label' => 'Treasure Chest', 'weight' => 8, 'payout_3' => 12, 'payout_4' => 30, 'payout_5' => 80, 'color' => '#A16207'],
            ['code' => 'crown', 'label' => 'Crown', 'weight' => 6, 'payout_3' => 15, 'payout_4' => 40, 'payout_5' => 100, 'color' => '#FBBF24'],
            ['code' => 'seven', 'label' => 'Seven', 'weight' => 5, 'payout_3' => 20, 'payout_4' => 50, 'payout_5' => 150, 'color' => '#DC2626'],
            ['code' => 'wild', 'label' => 'Wild', 'weight' => 5, 'payout_3' => 0, 'payout_4' => 0, 'payout_5' => 0, 'color' => '#22C55E', 'is_wild' => true],
            ['code' => 'scatter', 'label' => 'Scatter', 'weight' => 4, 'payout_3' => 0, 'payout_4' => 0, 'payout_5' => 0, 'color' => '#A855F7', 'is_scatter' => true],
            ['code' => 'bonus', 'label' => 'Bonus', 'weight' => 3, 'payout_3' => 0, 'payout_4' => 0, 'payout_5' => 0, 'color' => '#EC4899', 'is_bonus' => true],
            ['code' => 'jackpot', 'label' => 'Jackpot', 'weight' => 1, 'payout_3' => 0, 'payout_4' => 0, 'payout_5' => 0, 'color' => '#F97316', 'is_jackpot' => true],
        ];

        foreach ($symbols as $index => $symbol) {
            SlotMachineSymbol::query()->updateOrCreate(
                [
                    'config_id' => $config->getKey(),
                    'code' => $symbol['code'],
                ],
                array_merge($symbol, [
                    'sort_order' => $index,
                    'status' => SlotMachineSymbol::STATUS_ACTIVE,
                    'is_wild' => (bool) ($symbol['is_wild'] ?? false),
                    'is_scatter' => (bool) ($symbol['is_scatter'] ?? false),
                    'is_bonus' => (bool) ($symbol['is_bonus'] ?? false),
                    'is_jackpot' => (bool) ($symbol['is_jackpot'] ?? false),
                    'is_multiplier' => false,
                    'multiplier_value' => 1,
                    'icon' => null,
                    'metadata' => null,
                ]),
            );
        }

        $classicPaylines = [
            ['code' => 'c_mid', 'label' => 'Middle', 'positions' => [[0, 1], [1, 1], [2, 1]]],
            ['code' => 'c_top', 'label' => 'Top', 'positions' => [[0, 0], [1, 0], [2, 0]]],
            ['code' => 'c_bot', 'label' => 'Bottom', 'positions' => [[0, 2], [1, 2], [2, 2]]],
            ['code' => 'c_diag_down', 'label' => 'Diagonal Down', 'positions' => [[0, 0], [1, 1], [2, 2]]],
            ['code' => 'c_diag_up', 'label' => 'Diagonal Up', 'positions' => [[0, 2], [1, 1], [2, 0]]],
        ];

        foreach ($classicPaylines as $index => $line) {
            SlotMachinePayline::query()->updateOrCreate(
                [
                    'config_id' => $config->getKey(),
                    'code' => $line['code'],
                ],
                [
                    'label' => $line['label'],
                    'layout' => SlotMachinePayline::LAYOUT_CLASSIC,
                    'positions' => $line['positions'],
                    'sort_order' => $index,
                    'status' => SlotMachinePayline::STATUS_ACTIVE,
                ],
            );
        }

        $premiumPaylines = [
            ['code' => 'p_mid', 'label' => 'Middle', 'positions' => [[0, 1], [1, 1], [2, 1], [3, 1], [4, 1]]],
            ['code' => 'p_top', 'label' => 'Top', 'positions' => [[0, 0], [1, 0], [2, 0], [3, 0], [4, 0]]],
            ['code' => 'p_bot', 'label' => 'Bottom', 'positions' => [[0, 2], [1, 2], [2, 2], [3, 2], [4, 2]]],
            ['code' => 'p_v', 'label' => 'V Shape', 'positions' => [[0, 0], [1, 1], [2, 2], [3, 1], [4, 0]]],
            ['code' => 'p_inv_v', 'label' => 'Inverse V', 'positions' => [[0, 2], [1, 1], [2, 0], [3, 1], [4, 2]]],
            ['code' => 'p_zig', 'label' => 'Zigzag', 'positions' => [[0, 0], [1, 1], [2, 0], [3, 1], [4, 0]]],
            ['code' => 'p_zag', 'label' => 'Zagzig', 'positions' => [[0, 2], [1, 1], [2, 2], [3, 1], [4, 2]]],
            ['code' => 'p_flat_up', 'label' => 'Step Up', 'positions' => [[0, 2], [1, 2], [2, 1], [3, 0], [4, 0]]],
            ['code' => 'p_flat_down', 'label' => 'Step Down', 'positions' => [[0, 0], [1, 0], [2, 1], [3, 2], [4, 2]]],
        ];

        foreach ($premiumPaylines as $index => $line) {
            SlotMachinePayline::query()->updateOrCreate(
                [
                    'config_id' => $config->getKey(),
                    'code' => $line['code'],
                ],
                [
                    'label' => $line['label'],
                    'layout' => SlotMachinePayline::LAYOUT_PREMIUM,
                    'positions' => $line['positions'],
                    'sort_order' => $index,
                    'status' => SlotMachinePayline::STATUS_ACTIVE,
                ],
            );
        }

        $jackpots = [
            ['type' => SlotMachineJackpot::TYPE_GLOBAL, 'name' => 'Global Jackpot', 'pool' => 25000, 'seed' => 10000, 'contrib' => 0.5],
            ['type' => SlotMachineJackpot::TYPE_PROGRESSIVE, 'name' => 'Progressive Jackpot', 'pool' => 50000, 'seed' => 20000, 'contrib' => 1.0],
            ['type' => SlotMachineJackpot::TYPE_VIP, 'name' => 'VIP Jackpot', 'pool' => 75000, 'seed' => 30000, 'contrib' => 0.75, 'vip' => true],
            ['type' => SlotMachineJackpot::TYPE_DAILY, 'name' => 'Daily Jackpot', 'pool' => 5000, 'seed' => 5000, 'contrib' => 0.5, 'daily' => true],
            ['type' => SlotMachineJackpot::TYPE_ROOM, 'name' => 'Room Jackpot', 'pool' => 2000, 'seed' => 1000, 'contrib' => 0.25, 'room' => 'global'],
        ];

        foreach ($jackpots as $jackpot) {
            $attrs = [
                'config_id' => $config->getKey(),
                'type' => $jackpot['type'],
                'room_id' => $jackpot['room'] ?? null,
                'daily_date' => ($jackpot['daily'] ?? false) ? now()->toDateString() : null,
            ];

            SlotMachineJackpot::query()->updateOrCreate(
                $attrs,
                [
                    'name' => $jackpot['name'],
                    'pool_coins' => $jackpot['pool'],
                    'seed_coins' => $jackpot['seed'],
                    'min_pool_coins' => $jackpot['seed'],
                    'contribution_percent' => $jackpot['contrib'],
                    'requires_vip' => (bool) ($jackpot['vip'] ?? false),
                    'status' => SlotMachineJackpot::STATUS_ACTIVE,
                ],
            );
        }
    }
}
