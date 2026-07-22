<?php

namespace App\Features\LiveStreaming\Actions;

use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;

final readonly class EndLiveRoom
{
    public function __construct(private LiveStreamingService $live) {}

    public function execute(LiveRoom $room): LiveRoom
    {
        return $this->live->end($room);
    }
}
