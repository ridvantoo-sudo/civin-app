<?php

namespace App\Features\Games\Plugins\TreasureBox\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Models\GameLeaderboardEntry;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateTreasureBoxLeaderboard
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(TreasureCreated|TreasureJoined|TreasureRewarded $event): void
    {
        $treasure = $event->treasure;

        $user = match (true) {
            $event instanceof TreasureJoined => $event->participant->user,
            $event instanceof TreasureRewarded => $event->winner->user,
            default => $treasure->creator,
        };

        if (! $user instanceof User) {
            return;
        }

        $game = $treasure->game_id !== null
            ? Game::query()->find($treasure->game_id)
            : Game::query()->where('slug', Game::SLUG_TREASURE_BOX)->first();

        if ($game === null) {
            return;
        }

        $isWin = $event instanceof TreasureRewarded;
        $isJoin = $event instanceof TreasureJoined;
        $paysCoins = $treasure->paysCoins();

        $wagered = (! $isWin && ! $isJoin && $paysCoins) ? (int) $treasure->total_amount : 0;
        $reward = $isWin && $paysCoins ? $event->winner->rewardAmount() : 0;

        $this->games->bumpLeaderboard($user, $game, $wagered, $reward, $isWin);
        $this->bumpMonthly((string) $user->getKey(), (string) $game->getKey(), $wagered, $reward, $isWin, $isJoin);
    }

    private function bumpMonthly(
        string $userId,
        string $gameId,
        int $wagered,
        int $reward,
        bool $won,
        bool $joined,
    ): void {
        $periodKey = now()->format('Y-m');
        $net = $reward - $wagered;
        $scoreDelta = max(0, $reward) + ($won ? 25 : 0) + ($joined ? 1 : 0);

        DB::transaction(function () use ($userId, $gameId, $periodKey, $wagered, $net, $won, $scoreDelta): void {
            $entry = GameLeaderboardEntry::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'period' => 'monthly',
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
                'coins_wagered' => $locked->coins_wagered + $wagered,
                'coins_net' => $locked->coins_net + $net,
                'score' => $locked->score + $scoreDelta,
            ])->save();
        });
    }
}
