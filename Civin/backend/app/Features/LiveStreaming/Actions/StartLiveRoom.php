<?php

namespace App\Features\LiveStreaming\Actions;

use App\Features\LiveStreaming\DTOs\LiveRoomConnectionData;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;

final readonly class StartLiveRoom
{
    public function __construct(private LiveStreamingService $live) {}

    public function execute(LiveRoom $room): LiveRoomConnectionData
    {
        return $this->live->start($room);
    }
}
