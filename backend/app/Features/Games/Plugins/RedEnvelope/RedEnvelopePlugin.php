<?php

namespace App\Features\Games\Plugins\RedEnvelope;

use App\Features\Games\Core\Contracts\GamePlugin;
use App\Features\Games\Engines\EngineOutcome;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Users\Models\User;

/**
 * Red Envelope plugin. The generic engine contract stays unimplemented because
 * envelopes are a two-step create/claim flow rather than a single wager:
 * `/games/play` keeps its legacy GameService path and the production flow lives
 * in RedEnvelopeService behind `/games/red-envelope/*`.
 */
final class RedEnvelopePlugin implements GamePlugin
{
    public function slug(): string
    {
        return Game::SLUG_RED_ENVELOPE;
    }

    public function displayName(): string
    {
        return 'Red Envelope';
    }

    public function supportsMultiplayer(): bool
    {
        return false;
    }

    public function resolve(User $user, Game $game, int $betCoins, array $input): ?EngineOutcome
    {
        // Handled by GameService dedicated session flows.
        return null;
    }

    public function defaultConfig(): array
    {
        return [
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
            'expires_hours' => 24,
        ];
    }
}
