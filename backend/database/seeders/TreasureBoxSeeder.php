<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Plugins\TreasureBox\Models\TreasureBoxConfig;
use Illuminate\Database\Seeder;

class TreasureBoxSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_TREASURE_BOX)->first();

        $config = TreasureBoxConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Treasure Box Default',
                'description' => 'Default production Treasure Box configuration.',
                'version' => 1,
                'min_coins_amount' => 100,
                'max_coins_amount' => 1000000,
                'min_diamonds_amount' => 10,
                'max_diamonds_amount' => 100000,
                'max_winners' => 100,
                'min_countdown_seconds' => 5,
                'max_countdown_seconds' => 3600,
                'default_countdown_seconds' => 30,
                'min_claim_duration_seconds' => 30,
                'max_claim_duration_seconds' => 86400,
                'default_claim_duration_seconds' => 300,
                'max_created_per_day' => 50,
                'refund_unclaimed' => true,
                'allowed_reward_types' => TreasureBox::REWARD_TYPES,
                'allowed_types' => TreasureBox::TYPES,
                'allowed_visibilities' => TreasureBox::VISIBILITIES,
                'allowed_eligibilities' => TreasureBox::ELIGIBILITY_SCOPES,
                'allowed_room_types' => TreasureBox::ROOM_TYPES,
                'auto_rules' => [],
                'enabled' => true,
                'status' => TreasureBoxConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'TreasureBoxSeeder'],
            ],
        );

        if ($config->is_default) {
            TreasureBoxConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }
}
