<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Listeners;

use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRewardGranted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundFinished;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsStatistic;
use Illuminate\Support\Facades\DB;

final class UpdateRpsStatistics
{
    public function handle(RpsRoundFinished|RpsMatchFinished|RpsRewardGranted $event): void
    {
        $match = $event->match;

        if ($event instanceof RpsRoundFinished) {
            $this->applyRound($match, $event);

            return;
        }

        if ($event instanceof RpsRewardGranted) {
            $coins = $event->reward->currency === 'diamonds' ? 0 : (int) $event->reward->amount;
            $diamonds = $event->reward->currency === 'diamonds' ? (int) $event->reward->amount : 0;
            $this->bump((string) $event->reward->user_id, $match->config_id, [
                'coins_won' => $coins,
                'diamonds_won' => $diamonds,
            ]);

            return;
        }

        foreach ($match->participantIds() as $userId) {
            $isWinner = $match->winner_id !== null && (string) $match->winner_id === $userId;
            $isDraw = $match->winner_id === null;
            $forfeited = $match->forfeited_by !== null && (string) $match->forfeited_by === $userId;

            $this->bump($userId, $match->config_id, [
                'matches_played' => 1,
                'matches_won' => $isWinner ? 1 : 0,
                'matches_lost' => (! $isWinner && ! $isDraw) ? 1 : 0,
                'matches_drawn' => $isDraw ? 1 : 0,
                'forfeits' => $forfeited ? 1 : 0,
                'coins_wagered' => (int) $match->entry_fee_coins,
            ], streakDelta: $isDraw ? 0 : ($isWinner ? 1 : -1));
        }
    }

    private function applyRound(RpsMatch $match, RpsRoundFinished $event): void
    {
        $round = $event->round;
        $moves = RpsMove::query()->where('round_id', $round->getKey())->get();

        foreach ($match->participantIds() as $userId) {
            $won = $round->winner_id !== null && (string) $round->winner_id === $userId;
            $drawn = $round->winner_id === null;
            $move = $moves->firstWhere('user_id', $userId);

            $this->bump($userId, $match->config_id, [
                'rounds_played' => 1,
                'rounds_won' => $won ? 1 : 0,
                'rounds_lost' => (! $won && ! $drawn) ? 1 : 0,
                'rounds_drawn' => $drawn ? 1 : 0,
            ], responseMsSample: (int) ($move?->response_ms ?? 0), move: $move?->move);
        }
    }

    /** @param array<string, int> $increments */
    private function bump(
        string $userId,
        ?string $configId,
        array $increments,
        int $responseMsSample = 0,
        ?string $move = null,
        int $streakDelta = 0,
    ): void {
        DB::transaction(function () use ($userId, $configId, $increments, $responseMsSample, $move, $streakDelta): void {
            $stats = RpsStatistic::query()->firstOrCreate(
                ['user_id' => $userId, 'config_id' => $configId],
                [
                    'matches_played' => 0,
                    'matches_won' => 0,
                    'matches_lost' => 0,
                    'matches_drawn' => 0,
                    'forfeits' => 0,
                    'rounds_played' => 0,
                    'rounds_won' => 0,
                    'rounds_lost' => 0,
                    'rounds_drawn' => 0,
                    'current_streak' => 0,
                    'best_streak' => 0,
                    'win_rate' => 0,
                    'average_response_ms' => 0,
                    'coins_wagered' => 0,
                    'coins_won' => 0,
                    'diamonds_won' => 0,
                    'tournaments_played' => 0,
                    'tournaments_won' => 0,
                ],
            );

            $locked = RpsStatistic::query()->whereKey($stats->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            foreach ($increments as $column => $amount) {
                $locked->{$column} = (int) $locked->{$column} + $amount;
            }

            if ($streakDelta > 0) {
                $locked->current_streak = max(1, (int) $locked->current_streak + 1);
                $locked->best_streak = max((int) $locked->best_streak, (int) $locked->current_streak);
            } elseif ($streakDelta < 0) {
                $locked->current_streak = 0;
            }

            $played = max(0, (int) $locked->matches_played);
            if ($played > 0) {
                $locked->win_rate = round(((int) $locked->matches_won / $played) * 100, 2);
            }

            $rounds = max(0, (int) $locked->rounds_played);
            if ($responseMsSample > 0 && $rounds > 0) {
                $previousTotal = (float) $locked->average_response_ms * max(0, $rounds - 1);
                $locked->average_response_ms = round(($previousTotal + $responseMsSample) / $rounds, 2);
            }

            if ($move !== null) {
                $distribution = $locked->move_distribution ?? [];
                $distribution[$move] = (int) ($distribution[$move] ?? 0) + 1;
                $locked->move_distribution = $distribution;
            }

            $locked->save();
        });
    }
}
