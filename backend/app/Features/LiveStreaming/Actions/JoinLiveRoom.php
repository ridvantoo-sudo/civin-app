<?php

namespace App\Features\LiveStreaming\Actions;

use App\Features\LiveStreaming\DTOs\LiveRoomConnectionData;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Features\Users\Models\User;

final readonly class JoinLiveRoom
{
    public function __construct(private LiveStreamingService $live) {}

    public function execute(LiveRoom $room, User $viewer): LiveRoomConnectionData
    {
        return $this->live->join($room, $viewer);
    }
}
