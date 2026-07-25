<?php

namespace App\Features\Games\Plugins\LuckyWheel\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelJackpotWon;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelRewardWon;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordLuckyWheelRewardAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyWheelRewardWon|LuckyWheelJackpotWon $event): void
    {
        $spin = $event->spin;
        $game = $spin->game_id !== null
            ? Game::query()->find($spin->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->first();

        $eventName = $event instanceof LuckyWheelJackpotWon
            ? 'lucky_wheel_jackpot_won'
            : 'lucky_wheel_reward_won';

        $this->games->trackAnalytics($spin->user, $game, $eventName, [
            'spin_id' => $spin->id,
            'reward_type' => $spin->reward_type,
            'reward_amount' => $spin->reward_amount,
            'reward_currency' => $spin->reward_currency,
            'is_jackpot' => $spin->is_jackpot,
        ]);
    }
}
