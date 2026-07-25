<?php

namespace App\Features\Games\Plugins\LuckyNumber\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Repositories\Contracts\GameRepository;
use Illuminate\Support\Facades\DB;

final class UpdateLuckyNumberLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyNumberDrawFinished $event): void
    {
        $ticket = $event->ticket;
        $user = $ticket->user;
        if ($user === null) {
            return;
        }

        $game = $ticket->game_id !== null
            ? Game::query()->find($ticket->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_NUMBER)->first();

        if ($game === null) {
            return;
        }

        $won = $ticket->is_win || $ticket->is_jackpot;
        $this->games->bumpLeaderboard($user, $game, (int) $ticket->cost_coins, (int) $ticket->reward_coins, $won);
        $this->bumpPeriod($user->getKey(), $game->getKey(), 'monthly', now()->format('Y-m'), $ticket);
        $this->bumpPeriod($user->getKey(), $game->getKey(), 'weekly', now()->format('o-\\WW'), $ticket);
    }

    private function bumpPeriod(string $userId, string $gameId, string $period, string $periodKey, $ticket): void
    {
        $betCoins = (int) $ticket->cost_coins;
        $rewardCoins = (int) $ticket->reward_coins;
        $won = $ticket->is_win || $ticket->is_jackpot;
        $net = $rewardCoins - $betCoins;
        $scoreDelta = max(0, $rewardCoins) + ($won ? 25 : 0) + ($ticket->is_jackpot ? 100 : 0);

        DB::transaction(function () use ($userId, $gameId, $period, $periodKey, $betCoins, $net, $won, $scoreDelta): void {
            $entry = GameLeaderboardEntry::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'period' => $period,
                    'period_key' => $periodKey,
                ],
                [
                    'plays' => 0,
                    'wins' => 0,
                    'coins_wagered' => 0,
                    'coins_net' => 0,
                    'score' => 0,
                ],
            );

            $locked = GameLeaderboardEntry::query()->whereKey($entry->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                'plays' => $locked->plays + 1,
                'wins' => $locked->wins + ($won ? 1 : 0),
                'coins_wagered' => $locked->coins_wagered + $betCoins,
                'coins_net' => $locked->coins_net + $net,
                'score' => $locked->score + $scoreDelta,
            ])->save();
        });
    }
}
