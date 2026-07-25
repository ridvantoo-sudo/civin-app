<?php

namespace App\Features\Games\Plugins\Quiz\Listeners;

use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\QuizJoined;
use App\Features\Games\Plugins\Quiz\Events\QuizStarted;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;

/**
 * Session-level audit trail. Per-player QuizHistory rows are written in settleSession.
 */
final class RecordQuizHistory
{
    public function handle(QuizStarted|QuizJoined|QuizFinished $event): void
    {
        $session = $event->session;
        $entry = match (true) {
            $event instanceof QuizJoined => [
                'event' => 'joined',
                'at' => now()->toISOString(),
                'user_id' => $event->player->user_id,
                'player_id' => $event->player->id,
            ],
            $event instanceof QuizFinished => [
                'event' => 'finished',
                'at' => now()->toISOString(),
            ],
            default => [
                'event' => 'started',
                'at' => now()->toISOString(),
            ],
        };

        $locked = QuizSession::query()->whereKey($session->getKey())->first();
        if ($locked === null) {
            return;
        }

        $log = $locked->audit_log ?? [];
        $log[] = $entry;
        $locked->forceFill(['audit_log' => $log])->save();
    }
}
