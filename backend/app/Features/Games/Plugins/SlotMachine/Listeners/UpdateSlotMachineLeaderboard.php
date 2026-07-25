<?php

namespace App\Features\Games\Plugins\SlotMachine\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Repositories\Contracts\GameRepository;
use Illuminate\Support\Facades\DB;

final class UpdateSlotMachineLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(SlotSpinFinished $event): void
    {
        $spin = $event->spin;
        $user = $spin->user;
        if ($user === null) {
            return;
        }

        $game = $spin->game_id !== null
            ? Game::query()->find($spin->game_id)
            : Game::query()->where('slug', Game::SLUG_SLOT_MACHINE)->first();

        if ($game === null) {
            return;
        }

        $won = $spin->is_win || $spin->is_jackpot;
        $this->games->bumpLeaderboard($user, $game, (int) $spin->cost_coins, (int) $spin->reward_coins, $won);
        $this->bumpPeriod($user->getKey(), $game->getKey(), 'monthly', now()->format('Y-m'), $spin);
        $this->bumpPeriod($user->getKey(), $game->getKey(), 'weekly', now()->format('o-\\WW'), $spin);
    }

    private function bumpPeriod(string $userId, string $gameId, string $period, string $periodKey, $spin): void
    {
        $betCoins = (int) $spin->cost_coins;
        $rewardCoins = (int) $spin->reward_coins;
        $won = $spin->is_win || $spin->is_jackpot;
        $net = $rewardCoins - $betCoins;
        $scoreDelta = max(0, $rewardCoins) + ($won ? 25 : 0) + ($spin->is_jackpot ? 100 : 0);

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
