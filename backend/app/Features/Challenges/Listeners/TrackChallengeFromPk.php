<?php

namespace App\Features\Challenges\Listeners;

use App\Features\Challenges\Services\ChallengeService;
use App\Features\PkBattle\Events\PkFinished;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class TrackChallengeFromPk implements ShouldQueueAfterCommit
{
    public function __construct(private readonly ChallengeService $challenges) {}

    public function handle(PkFinished $event): void
    {
        $battle = $event->battle;
        if ($battle->status !== PkBattle::STATUS_FINISHED) {
            return;
        }

        foreach ([$battle->host_a_id, $battle->host_b_id] as $hostId) {
            if ($hostId === null) {
                continue;
            }

            $host = User::query()->find($hostId);
            if ($host instanceof User) {
                $this->challenges->trackPk($host);
            }
        }
    }
}
