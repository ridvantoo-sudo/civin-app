<?php

namespace App\Features\VoicePkBattle\Events;

final class VoicePkScoreUpdated extends VoicePkBattleBroadcast
{
    public function broadcastAs(): string
    {
        return 'voice-pk.score.updated';
    }
}
