<?php

namespace App\Features\Games\Plugins\Ludo\Listeners;

use App\Features\Games\Plugins\Ludo\Events\LeaderboardUpdated;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchFinished;

final class UpdateLudoLeaderboard
{
    public function handle(LudoMatchFinished $event): void
    {
        LeaderboardUpdated::dispatch($event->subject, ['reason' => 'match_finished']);
    }
}
