<?php

namespace App\Features\VoiceRoom\Actions;

use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Services\VoiceRoomService;

final readonly class EndVoiceRoom
{
    public function __construct(private VoiceRoomService $voice) {}

    public function execute(VoiceRoom $room): VoiceRoom
    {
        return $this->voice->end($room);
    }
}
