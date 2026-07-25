<?php

namespace App\Features\VoicePkBattle\Events;

final class VoicePkInvited extends VoicePkBattleBroadcast
{
    public function broadcastAs(): string
    {
        return 'voice-pk.invited';
    }
}
