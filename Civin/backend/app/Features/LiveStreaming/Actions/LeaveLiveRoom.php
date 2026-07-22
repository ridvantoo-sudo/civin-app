<?php

namespace App\Features\LiveStreaming\Actions;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Features\Users\Models\User;

final readonly class LeaveLiveRoom
{
    public function __construct(private LiveStreamingService $live) {}

    public function execute(LiveRoom $room, User $viewer): LiveRoom
    {
        return $this->live->leave($room, $viewer);
    }
}
