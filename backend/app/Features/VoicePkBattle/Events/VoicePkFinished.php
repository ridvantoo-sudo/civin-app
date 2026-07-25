<?php

namespace App\Features\VoicePkBattle\Events;

final class VoicePkFinished extends VoicePkBattleBroadcast
{
    public function broadcastAs(): string
    {
        return 'voice-pk.finished';
    }
}
