<?php

namespace App\Features\Games\Plugins\LuckyWheel\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelPlayed;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordLuckyWheelAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyWheelPlayed $event): void
    {
        $spin = $event->spin;
        $game = $spin->game_id !== null
            ? Game::query()->find($spin->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->first();

        $this->games->trackAnalytics($spin->user, $game, 'lucky_wheel_played', [
            'spin_id' => $spin->id,
            'idempotency_key' => $spin->idempotency_key,
            'cost_coins' => $spin->cost_coins,
            'reward_type' => $spin->reward_type,
            'reward_amount' => $spin->reward_amount,
            'is_jackpot' => $spin->is_jackpot,
            'sector_position' => $spin->sector_position,
        ]);
    }
}
