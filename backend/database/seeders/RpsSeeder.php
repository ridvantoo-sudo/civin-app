<?php

namespace Database\Seeders;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use Illuminate\Database\Seeder;

class RpsSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();

        $config = RpsConfig::query()->updateOrCreate(
            ['slug' => 'default'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Rock Paper Scissors Default',
                'description' => 'Default production Rock Paper Scissors configuration.',
                'version' => 1,
                'min_entry_fee_coins' => 0,
                'max_entry_fee_coins' => 5000,
                'default_entry_fee_coins' => 10,
                'default_round_timeout_seconds' => 15,
                'min_round_timeout_seconds' => 5,
                'max_round_timeout_seconds' => 120,
                'accept_timeout_seconds' => 120,
                'match_timeout_seconds' => 1800,
                'max_rounds' => 11,
                'forfeit_after_missed_rounds' => 2,
                'rematch_limit' => 5,
                'max_created_per_day' => 200,
                'cooldown_seconds' => 0,
                'house_rake_bps' => 0,
                'vip_reward_multiplier' => 1.25,
                'modes_enabled' => RpsConfig::MODES,
                'matchmaking_enabled' => RpsConfig::MATCHMAKING,
                'reward_types_enabled' => RpsConfig::REWARD_TYPES,
                'visibilities_enabled' => RpsConfig::VISIBILITIES,
                'eligibilities_enabled' => RpsConfig::ELIGIBILITIES,
                'tournament_formats_enabled' => RpsConfig::TOURNAMENT_FORMATS,
                'move_rules' => RpsConfig::DEFAULT_MOVE_RULES,
                'mode_rounds' => RpsConfig::DEFAULT_MODE_ROUNDS,
                'enabled' => true,
                'status' => RpsConfig::STATUS_ACTIVE,
                'is_default' => true,
                'metadata' => ['source' => 'RpsSeeder'],
            ],
        );

        if ($config->is_default) {
            RpsConfig::query()
                ->where('id', '!=', $config->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // A five-move variant proving the rule set is data, not code.
        RpsConfig::query()->updateOrCreate(
            ['slug' => 'lizard-spock'],
            [
                'game_id' => $game?->getKey(),
                'name' => 'Rock Paper Scissors Lizard Spock',
                'description' => 'Extended five-move rule set, disabled by default.',
                'version' => 1,
                'min_entry_fee_coins' => 0,
                'max_entry_fee_coins' => 5000,
                'default_entry_fee_coins' => 10,
                'modes_enabled' => RpsConfig::MODES,
                'matchmaking_enabled' => RpsConfig::MATCHMAKING,
                'reward_types_enabled' => RpsConfig::REWARD_TYPES,
                'visibilities_enabled' => RpsConfig::VISIBILITIES,
                'eligibilities_enabled' => RpsConfig::ELIGIBILITIES,
                'tournament_formats_enabled' => RpsConfig::TOURNAMENT_FORMATS,
                'move_rules' => [
                    'moves' => ['rock', 'paper', 'scissors', 'lizard', 'spock'],
                    'beats' => [
                        'rock' => ['scissors', 'lizard'],
                        'paper' => ['rock', 'spock'],
                        'scissors' => ['paper', 'lizard'],
                        'lizard' => ['paper', 'spock'],
                        'spock' => ['rock', 'scissors'],
                    ],
                ],
                'mode_rounds' => RpsConfig::DEFAULT_MODE_ROUNDS,
                'enabled' => false,
                'status' => RpsConfig::STATUS_INACTIVE,
                'is_default' => false,
                'metadata' => ['source' => 'RpsSeeder', 'variant' => 'lizard_spock'],
            ],
        );

        RpsTournament::query()->updateOrCreate(
            ['slug' => 'rps-daily-cup'],
            [
                'config_id' => $config->getKey(),
                'game_id' => $game?->getKey(),
                'name' => 'Daily RPS Cup',
                'description' => 'Free-to-enter single elimination cup that runs every day.',
                'format' => RpsTournament::FORMAT_SINGLE_ELIMINATION,
                'status' => RpsTournament::STATUS_REGISTRATION,
                'mode' => RpsMatch::MODE_BEST_OF_3,
                'max_players' => 16,
                'min_players' => 2,
                'entry_fee_coins' => 0,
                'reward_type' => 'coins',
                'reward_pool' => 0,
                'prize_structure' => [['rank' => 1, 'percent' => 100]],
                'is_daily' => true,
                'is_scheduled' => true,
                'registration_opens_at' => now(),
                'registration_closes_at' => now()->addDay(),
                'scheduled_at' => now()->addDay(),
                'metadata' => ['source' => 'RpsSeeder'],
            ],
        );

        RpsTournament::query()->updateOrCreate(
            ['slug' => 'rps-vip-arena-cup'],
            [
                'config_id' => $config->getKey(),
                'game_id' => $game?->getKey(),
                'name' => 'VIP Arena Cup',
                'description' => 'Double elimination cup restricted to active VIP members.',
                'format' => RpsTournament::FORMAT_DOUBLE_ELIMINATION,
                'status' => RpsTournament::STATUS_SCHEDULED,
                'mode' => RpsMatch::MODE_BEST_OF_5,
                'max_players' => 8,
                'min_players' => 2,
                'entry_fee_coins' => 100,
                'reward_type' => 'coins',
                'reward_pool' => 0,
                'prize_structure' => [['rank' => 1, 'percent' => 100]],
                'is_vip' => true,
                'is_scheduled' => true,
                'registration_opens_at' => now(),
                'registration_closes_at' => now()->addDays(2),
                'scheduled_at' => now()->addDays(2),
                'metadata' => ['source' => 'RpsSeeder'],
            ],
        );
    }
}
