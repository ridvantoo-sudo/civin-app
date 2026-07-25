<?php

namespace App\Features\Games\Plugins\Ludo;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Ludo\Models\LudoConfig;
use App\Features\Games\Plugins\Ludo\Support\LudoRuleEngine;
use App\Features\Users\Models\User;

final class LudoPlugin implements GamePlugin
{
    public function slug(): string
    {
        return Game::SLUG_LUDO;
    }

    public function displayName(): string
    {
        return 'Ludo';
    }

    public function supportsMultiplayer(): bool
    {
        return true;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        // Handled by GameService dedicated session flows.
        return null;
    }

    public function defaultConfig(): array
    {
        return [
            'min_entry_fee_coins' => 0,
            'max_entry_fee_coins' => 5000,
            'default_entry_fee_coins' => 10,
            'ready_timeout_seconds' => 60,
            'turn_timeout_seconds' => 30,
            'match_timeout_seconds' => 3600,
            'forfeit_after_afk' => 3,
            'house_rake_bps' => 0,
            'modes_enabled' => LudoConfig::MODES,
            'matchmaking_enabled' => LudoConfig::MATCHMAKING,
            'tournament_formats_enabled' => LudoConfig::TOURNAMENT_FORMATS,
            'rules' => LudoRuleEngine::DEFAULT_RULES + [
                'first_turn' => 'red',
                'random_first_turn' => false,
                'timeout_tie_strategy' => 'shared_pot',
            ],
        ];
    }
}
