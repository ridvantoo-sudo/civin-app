<?php

namespace App\Features\Games\Plugins\LuckyWheel\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelPlayed;
use App\Features\Games\Repositories\Contracts\GameRepository;
use Illuminate\Support\Facades\DB;

final class UpdateLuckyWheelLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyWheelPlayed $event): void
    {
        $spin = $event->spin;
        $user = $spin->user;
        if ($user === null) {
            return;
        }

        $game = $spin->game_id !== null
            ? Game::query()->find($spin->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->first();

        if ($game === null) {
            return;
        }

        $rewardCoins = $spin->reward_currency === 'coins' ? (int) $spin->reward_amount : 0;
        $won = $rewardCoins > $spin->cost_coins || $spin->is_jackpot;

        $this->games->bumpLeaderboard($user, $game, (int) $spin->cost_coins, $rewardCoins, $won);
        $this->bumpMonthly($user->getKey(), $game->getKey(), (int) $spin->cost_coins, $rewardCoins, $won);
    }

    private function bumpMonthly(string $userId, string $gameId, int $betCoins, int $rewardCoins, bool $won): void
    {
        $period = 'monthly';
        $periodKey = now()->format('Y-m');
        $net = $rewardCoins - $betCoins;
        $scoreDelta = max(0, $rewardCoins) + ($won ? 25 : 0);

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
