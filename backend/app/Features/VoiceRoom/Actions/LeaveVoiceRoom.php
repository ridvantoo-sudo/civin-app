<?php

namespace App\Features\VoiceRoom\Actions;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Services\VoiceRoomService;

final readonly class LeaveVoiceRoom
{
    public function __construct(private VoiceRoomService $voice) {}

    public function execute(VoiceRoom $room, User $user): VoiceRoom
    {
        return $this->voice->leave($room, $user);
    }
}
