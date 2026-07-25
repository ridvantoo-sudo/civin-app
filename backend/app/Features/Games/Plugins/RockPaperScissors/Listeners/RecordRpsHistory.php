<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Listeners;

use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeAccepted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundFinished;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;

/**
 * Match-level audit trail. Per-user rps_histories rows are written during
 * settlement inside RpsMatchService.
 */
final class RecordRpsHistory
{
    public function handle(RpsChallengeAccepted|RpsMatchStarted|RpsRoundFinished|RpsMatchFinished $event): void
    {
        $entry = match (true) {
            $event instanceof RpsChallengeAccepted => [
                'event' => 'accepted',
                'at' => now()->toISOString(),
                'opponent_id' => $event->match->opponent_id,
            ],
            $event instanceof RpsRoundFinished => [
                'event' => 'round_finished',
                'at' => now()->toISOString(),
                'round_index' => (int) $event->round->round_index,
                'round_result' => $event->round->result,
            ],
            $event instanceof RpsMatchFinished => [
                'event' => 'finished',
                'at' => now()->toISOString(),
                'result' => $event->match->result,
                'winner_id' => $event->match->winner_id,
            ],
            default => [
                'event' => 'started',
                'at' => now()->toISOString(),
            ],
        };

        $match = RpsMatch::query()->whereKey($event->match->getKey())->first();
        if ($match === null) {
            return;
        }

        $log = $match->audit_log ?? [];
        $log[] = $entry;
        $match->forceFill(['audit_log' => $log])->save();
    }
}
