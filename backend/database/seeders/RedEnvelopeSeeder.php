<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Plugins\RedEnvelope\Models\RedEnvelopeConfig;
use Illuminate\Database\Seeder;

class RedEnvelopeSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_RED_ENVELOPE)->first();

        $config = RedEnvelopeConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Red Envelope Default',
                'description' => 'Default production Red Envelope configuration.',
                'version' => 1,
                'min_coins_amount' => 100,
                'max_coins_amount' => 1000000,
                'min_diamonds_amount' => 10,
                'max_diamonds_amount' => 100000,
                'max_winners' => 100,
                'min_duration_seconds' => 60,
                'max_duration_seconds' => 86400,
                'default_duration_seconds' => 3600,
                'max_created_per_day' => 50,
                'refund_unclaimed' => true,
                'allowed_reward_types' => RedEnvelope::REWARD_TYPES,
                'allowed_distribution_types' => RedEnvelope::DISTRIBUTION_TYPES,
                'allowed_visibilities' => RedEnvelope::VISIBILITIES,
                'allowed_claim_scopes' => RedEnvelope::CLAIM_SCOPES,
                'allowed_room_types' => RedEnvelope::ROOM_TYPES,
                'enabled' => true,
                'status' => RedEnvelopeConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'RedEnvelopeSeeder'],
            ],
        );

        if ($config->is_default) {
            RedEnvelopeConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }
}
