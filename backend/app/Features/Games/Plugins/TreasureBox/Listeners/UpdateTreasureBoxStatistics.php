<?php

namespace App\Features\Games\Plugins\TreasureBox\Listeners;

use App\Features\Games\Models\TreasureBoxStatistic;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use Illuminate\Support\Facades\DB;

final class UpdateTreasureBoxStatistics
{
    public function handle(TreasureCreated|TreasureJoined|TreasureRewarded $event): void
    {
        $treasure = $event->treasure;

        if ($event instanceof TreasureCreated) {
            $this->bump($treasure->creator_id, [
                'treasures_created' => 1,
                'total_spent_amount' => (int) $treasure->total_amount,
            ]);

            return;
        }

        if ($event instanceof TreasureJoined) {
            $joinSeconds = 0;
            if ($treasure->opens_at !== null && $event->participant->joined_at !== null) {
                $joinSeconds = max(0, $treasure->opens_at->diffInSeconds($event->participant->joined_at));
            }

            $this->bump($event->participant->user_id, [
                'treasures_joined' => 1,
            ], $joinSeconds);

            return;
        }

        $this->bump($event->winner->user_id, [
            'treasures_won' => 1,
            'total_reward_amount' => $event->winner->rewardAmount(),
            'jackpots_won' => $event->winner->is_jackpot ? 1 : 0,
        ]);
    }

    /** @param  array<string, int>  $increments */
    private function bump(?string $userId, array $increments, int $joinSecondsSample = 0): void
    {
        if ($userId === null) {
            return;
        }

        DB::transaction(function () use ($userId, $increments, $joinSecondsSample): void {
            $stats = TreasureBoxStatistic::query()->firstOrCreate(
                ['user_id' => $userId],
                [
                    'scope' => TreasureBoxStatistic::SCOPE_USER,
                    'treasures_created' => 0,
                    'treasures_joined' => 0,
                    'treasures_won' => 0,
                    'total_reward_amount' => 0,
                    'total_spent_amount' => 0,
                    'jackpots_won' => 0,
                    'average_join_seconds' => 0,
                ],
            );

            $locked = TreasureBoxStatistic::query()->whereKey($stats->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            foreach ($increments as $column => $amount) {
                $locked->{$column} = (int) $locked->{$column} + $amount;
            }

            if ($joinSecondsSample > 0 || isset($increments['treasures_joined'])) {
                $joined = max(1, (int) $locked->treasures_joined);
                $prevTotal = (int) $locked->average_join_seconds * max(0, $joined - 1);
                $locked->average_join_seconds = (int) floor(($prevTotal + $joinSecondsSample) / $joined);
            }

            $locked->save();
        });
    }
}
