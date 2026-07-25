<?php

namespace App\Features\Games\Plugins\RockPaperScissors;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\RockPaperScissorsEngine;
use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Users\Models\User;

/**
 * Rock Paper Scissors plugin. Legacy `/games/play` still delegates to the
 * single-player RockPaperScissorsEngine; the production player-versus-player
 * flow lives behind `/games/rps/*`.
 */
final class RockPaperScissorsPlugin implements GamePlugin
{
    public function __construct(private readonly RockPaperScissorsEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_ROCK_PAPER_SCISSORS;
    }

    public function displayName(): string
    {
        return 'Rock Paper Scissors';
    }

    public function supportsMultiplayer(): bool
    {
        return true;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        return $this->engine->resolve($user, $game, $betCoins, $input);
    }

    public function defaultConfig(): array
    {
        return [
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
        ];
    }
}
