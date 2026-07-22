<?php

namespace App\Features\LiveStreaming\Actions;

use App\Features\LiveStreaming\DTOs\CreateLiveRoomData;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Features\Users\Models\User;

final readonly class CreateLiveRoom
{
    public function __construct(private LiveStreamingService $live) {}

    public function execute(User $host, CreateLiveRoomData $data): LiveRoom
    {
        return $this->live->create($host, $data);
    }
}
