<?php

namespace App\Features\VoiceRoom\Actions;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\DTOs\VoiceRoomConnectionData;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Services\VoiceRoomService;

final readonly class JoinVoiceRoom
{
    public function __construct(private VoiceRoomService $voice) {}

    public function execute(VoiceRoom $room, User $user): VoiceRoomConnectionData
    {
        return $this->voice->join($room, $user);
    }
}
