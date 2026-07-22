<?php

namespace App\Features\LiveStreaming\Services;

use App\Features\LiveChat\Services\LiveChatService;
use App\Features\LiveStreaming\DTOs\CreateLiveRoomData;
use App\Features\LiveStreaming\DTOs\LiveRoomConnectionData;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Events\LiveStarted;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Repositories\Contracts\LiveRoomRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final readonly class LiveStreamingService
{
    public function __construct(
        private LiveRoomRepository $rooms,
        private AgoraService $agora,
        private LiveChatService $chat,
    ) {}

    public function create(User $host, CreateLiveRoomData $data): LiveRoom
    {
        $roomId = (string) Str::uuid();

        do {
            $streamUid = random_int(1, 4294967295);
        } while ($this->rooms->streamUidExists($streamUid));

        $room = $this->rooms->create(
            $host,
            $data,
            $roomId,
            $this->agora->createChannel($roomId),
            $streamUid,
        );

        $this->chat->initializeRoom($room);

        return $room;
    }

    public function start(LiveRoom $room): LiveRoomConnectionData
    {
        $rtc = $this->agora->generateHostToken($room->agora_channel_name, $room->stream_uid);
        $started = $this->rooms->start($room);
        LiveStarted::dispatch($started->getKey(), $started->host_id);

        return new LiveRoomConnectionData($started, $rtc);
    }

    public function join(LiveRoom $room, User $viewer): LiveRoomConnectionData
    {
        $rtc = $this->agora->generateViewerToken($room->agora_channel_name, (string) $viewer->getKey());
        $result = $this->rooms->join($room, $viewer);

        if ($result['changed']) {
            $this->chat->recordViewerJoined($result['room'], $viewer, $result['room']->viewer_count);
        }

        return new LiveRoomConnectionData($result['room'], $rtc);
    }

    public function leave(LiveRoom $room, User $viewer): LiveRoom
    {
        $result = $this->rooms->leave($room, $viewer);

        if ($result['changed']) {
            $this->chat->recordViewerLeft($result['room'], $viewer, $result['room']->viewer_count);
        }

        return $result['room'];
    }

    public function end(LiveRoom $room): LiveRoom
    {
        $ended = $this->rooms->end($room);
        LiveEnded::dispatch(
            $ended->getKey(),
            $ended->host_id,
            $ended->session->duration,
        );

        return $ended;
    }

    public function live(int $perPage): LengthAwarePaginator
    {
        return $this->rooms->live($perPage);
    }

    public function show(LiveRoom $room): LiveRoom
    {
        return $this->rooms->show($room);
    }
}
