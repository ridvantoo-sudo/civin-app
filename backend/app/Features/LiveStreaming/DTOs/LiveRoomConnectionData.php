<?php

namespace App\Features\LiveStreaming\DTOs;

use App\Features\LiveStreaming\Models\LiveRoom;

final readonly class LiveRoomConnectionData
{
    public function __construct(
        public LiveRoom $room,
        public RtcConnectionData $rtc,
    ) {}
}
