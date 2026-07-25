<?php

namespace App\Features\Games\Plugins\TreasureBox;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Engines\TreasureBoxEngine;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\TreasureBox;
use App\Features\Users\Models\User;

/**
 * Treasure Box plugin. Legacy `/games/play` still delegates to TreasureBoxEngine.
 * The production create/join/draw flow lives in TreasureBoxService behind
 * `/games/treasure-box/*`.
 */
final class TreasureBoxPlugin implements GamePlugin
{
    public function __construct(private readonly TreasureBoxEngine $engine) {}

    public function slug(): string
    {
        return Game::SLUG_TREASURE_BOX;
    }

    public function displayName(): string
    {
        return 'Treasure Box';
    }

    public function supportsMultiplayer(): bool
    {
        return false;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        return $this->engine->resolve($user, $game, $betCoins, $input);
    }

    public function defaultConfig(): array
    {
        return [
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
            'allowed_distribution_types' => TreasureBox::DISTRIBUTION_TYPES,
        ];
    }
}
