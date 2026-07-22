<?php

namespace App\Features\VoiceRoom\DTOs;

use App\Features\VoiceRoom\Models\VoiceRoom;

final readonly class VoiceRoomConnectionData
{
    public function __construct(
        public VoiceRoom $room,
        public VoiceRtcConnectionData $rtc,
    ) {}
}
