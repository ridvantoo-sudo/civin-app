<?php

namespace App\Features\Games\Plugins\LuckyDice\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDicePlayed;
use App\Features\Games\Repositories\Contracts\GameRepository;
use Illuminate\Support\Facades\DB;

final class UpdateLuckyDiceLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(LuckyDicePlayed $event): void
    {
        $bet = $event->bet;
        $user = $bet->user;
        if ($user === null) {
            return;
        }

        $game = $bet->game_id !== null
            ? Game::query()->find($bet->game_id)
            : Game::query()->where('slug', Game::SLUG_LUCKY_DICE)->first();

        if ($game === null) {
            return;
        }

        $rewardCoins = in_array($bet->reward_type, ['coins', 'jackpot'], true) ? (int) $bet->reward_amount : 0;
        $won = $bet->is_win || $bet->is_jackpot;

        $this->games->bumpLeaderboard($user, $game, (int) $bet->bet_amount, $rewardCoins, $won);
        $this->bumpMonthly($user->getKey(), $game->getKey(), (int) $bet->bet_amount, $rewardCoins, $won);
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
