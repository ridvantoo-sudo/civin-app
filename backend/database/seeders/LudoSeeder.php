<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Ludo\LudoPlugin;
use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Models\LudoTournament;
use Illuminate\Database\Seeder;

final class LudoSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_LUDO)->first();
        $defaults = app(LudoPlugin::class)->defaultConfig();
        $config = LudoConfig::query()->updateOrCreate(['slug' => 'default'], [
            'game_id' => $game?->getKey(), 'name' => 'Ludo Default',
            'description' => 'Production classic Ludo configuration.', 'version' => 1,
            ...$defaults, 'enabled' => true, 'is_default' => true,
            'status' => LudoConfig::STATUS_ACTIVE, 'metadata' => ['source' => self::class],
        ]);
        LudoConfig::query()->whereKeyNot($config->getKey())->update(['is_default' => false]);

        LudoTournament::query()->updateOrCreate(['slug' => 'ludo-daily-cup'], [
            'config_id' => $config->getKey(), 'game_id' => $game?->getKey(),
            'name' => 'Daily Ludo Cup', 'description' => 'Daily four-player Ludo tournament.',
            'format' => 'single_elimination', 'status' => 'registration', 'mode' => 'classic_4',
            'max_players' => 16, 'min_players' => 4, 'entry_fee_coins' => 0,
            'reward_pool' => 0, 'prize_structure' => [['rank' => 1, 'percent' => 100]],
            'participant_ids' => [], 'is_daily' => true, 'is_scheduled' => true,
            'registration_opens_at' => now(), 'registration_closes_at' => now()->addDay(),
            'scheduled_at' => now()->addDay(), 'metadata' => ['source' => self::class],
        ]);
    }
}
