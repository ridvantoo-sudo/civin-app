<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\RockPaperScissors\Events\LeaderboardUpdated;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRewardGranted;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts\RpsRepository;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;

/**
 * Bumps the shared game leaderboard and re-broadcasts the daily RPS board.
 * Period boards themselves are computed from rps_histories.
 */
final class UpdateRpsLeaderboard
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly RpsRepository $matches,
    ) {}

    public function handle(RpsMatchFinished|RpsRewardGranted $event): void
    {
        $match = $event->match;
        $game = $match->game_id !== null
            ? Game::query()->find($match->game_id)
            : Game::query()->where('slug', Game::SLUG_ROCK_PAPER_SCISSORS)->first();

        if ($game === null) {
            return;
        }

        if ($event instanceof RpsRewardGranted) {
            $user = User::query()->find($event->reward->user_id);
            if ($user === null) {
                return;
            }

            $coins = $event->reward->currency === 'diamonds' ? 0 : (int) $event->reward->amount;
            $this->games->bumpLeaderboard($user, $game, 0, $coins, true);

            return;
        }

        foreach ($match->participantIds() as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $won = $match->winner_id !== null && (string) $match->winner_id === $userId;
            $this->games->bumpLeaderboard($user, $game, (int) $match->entry_fee_coins, 0, $won);
        }

        LeaderboardUpdated::dispatch(
            'daily',
            $this->matches->leaderboard('daily', 20)->values()->all(),
        );
    }
}
