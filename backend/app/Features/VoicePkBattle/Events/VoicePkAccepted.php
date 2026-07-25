<?php

namespace App\Features\VoicePkBattle\Events;

final class VoicePkAccepted extends VoicePkBattleBroadcast
{
    public function broadcastAs(): string
    {
        return 'voice-pk.accepted';
    }
}
