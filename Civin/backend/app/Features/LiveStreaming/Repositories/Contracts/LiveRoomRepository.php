<?php

namespace App\Features\LiveStreaming\Repositories\Contracts;

use App\Features\LiveStreaming\DTOs\CreateLiveRoomData;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LiveRoomRepository
{
    public function create(
        User $host,
        CreateLiveRoomData $data,
        string $roomId,
        string $channel,
        int $streamUid,
    ): LiveRoom;

    public function start(LiveRoom $room): LiveRoom;

    /** @return array{room: LiveRoom, changed: bool} */
    public function join(LiveRoom $room, User $viewer): array;

    /** @return array{room: LiveRoom, changed: bool} */
    public function leave(LiveRoom $room, User $viewer): array;

    public function end(LiveRoom $room): LiveRoom;

    public function live(int $perPage): LengthAwarePaginator;

    public function show(LiveRoom $room): LiveRoom;

    public function streamUidExists(int $uid): bool;
}
