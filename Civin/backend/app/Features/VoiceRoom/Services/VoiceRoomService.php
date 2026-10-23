<?php

namespace App\Features\VoiceRoom\Services;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\DTOs\CreateVoiceRoomData;
use App\Features\VoiceRoom\DTOs\SeatActionData;
use App\Features\VoiceRoom\DTOs\VoiceRoomConnectionData;
use App\Features\VoiceRoom\Events\SeatUpdated;
use App\Features\VoiceRoom\Events\SpeakerJoined;
use App\Features\VoiceRoom\Events\SpeakerRemoved;
use App\Features\VoiceRoom\Events\VoiceRoomEnded;
use App\Features\VoiceRoom\Events\VoiceRoomStarted;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSeat;
use App\Features\VoiceRoom\Repositories\Contracts\VoiceRoomRepository;
use Illuminate\Support\Str;

final readonly class VoiceRoomService
{
    public function __construct(
        private VoiceRoomRepository $rooms,
        private VoiceAgoraService $agora,
    ) {}

    public function create(User $host, CreateVoiceRoomData $data): VoiceRoomConnectionData
    {
        $roomId = (string) Str::uuid();

        do {
            $hostUid = random_int(1, 4294967295);
        } while ($this->rooms->hostUidExists($hostUid));

        $room = $this->rooms->create(
            $host,
            $data,
            $roomId,
            $this->agora->createChannel($roomId),
            $hostUid,
        );

        VoiceRoomStarted::dispatch($room->getKey(), $room->host_id, $room->seat_count);

        $rtc = $this->agora->generateHostToken($room->agora_channel_name, $room->host_uid);

        return new VoiceRoomConnectionData($room, $rtc);
    }

    public function join(VoiceRoom $room, User $user): VoiceRoomConnectionData
    {
        $result = $this->rooms->join($room, $user);
        $joined = $result['room'];
        $rtc = $this->tokenFor($joined, $user);

        return new VoiceRoomConnectionData($joined, $rtc);
    }

    public function leave(VoiceRoom $room, User $user): VoiceRoom
    {
        $result = $this->rooms->leave($room, $user);

        if ($result['freed_seat'] instanceof VoiceSeat) {
            $seat = $result['freed_seat'];
            SeatUpdated::dispatch(
                $result['room']->getKey(),
                $seat->seat_index,
                $seat->status,
                $seat->user_id,
                $seat->is_muted,
            );

            if ($result['was_speaker']) {
                SpeakerRemoved::dispatch($result['room']->getKey(), $user->getKey(), $seat->seat_index);
            }
        }

        return $result['room'];
    }

    public function requestSeat(VoiceRoom $room, User $user, SeatActionData $data): VoiceRoom
    {
        $seat = $this->rooms->requestSeat($room, $user, $data);

        SeatUpdated::dispatch(
            $room->getKey(),
            $seat->seat_index,
            $seat->status,
            $seat->user_id,
            $seat->is_muted,
        );

        return $this->rooms->show($room->fresh());
    }

    public function approveSeat(VoiceRoom $room, SeatActionData $data): VoiceRoom
    {
        $seat = $this->rooms->approveSeat($room, $data);

        SeatUpdated::dispatch(
            $room->getKey(),
            $seat->seat_index,
            $seat->status,
            $seat->user_id,
            $seat->is_muted,
        );
        SpeakerJoined::dispatch($room->getKey(), (string) $seat->user_id, $seat->seat_index);

        return $this->rooms->show($room->fresh());
    }

    public function rejectSeat(VoiceRoom $room, SeatActionData $data): VoiceRoom
    {
        $seat = $this->rooms->rejectSeat($room, $data);

        SeatUpdated::dispatch(
            $room->getKey(),
            $seat->seat_index,
            $seat->status,
            $seat->user_id,
            $seat->is_muted,
        );

        return $this->rooms->show($room->fresh());
    }

    public function removeSpeaker(VoiceRoom $room, SeatActionData $data): VoiceRoom
    {
        $result = $this->rooms->removeSpeaker($room, $data);
        $seat = $result['seat'];

        SeatUpdated::dispatch(
            $result['room']->getKey(),
            $seat->seat_index,
            $seat->status,
            $seat->user_id,
            $seat->is_muted,
        );
        SpeakerRemoved::dispatch($result['room']->getKey(), $result['user_id'], $seat->seat_index);

        return $result['room'];
    }

    public function muteSpeaker(VoiceRoom $room, SeatActionData $data): VoiceRoom
    {
        $seat = $this->rooms->muteSpeaker($room, $data);

        SeatUpdated::dispatch(
            $room->getKey(),
            $seat->seat_index,
            $seat->status,
            $seat->user_id,
            $seat->is_muted,
        );

        return $this->rooms->show($room->fresh());
    }

    public function end(VoiceRoom $room): VoiceRoom
    {
        $ended = $this->rooms->end($room);

        VoiceRoomEnded::dispatch(
            $ended->getKey(),
            $ended->host_id,
            $ended->session->duration,
        );

        return $ended;
    }

    public function show(VoiceRoom $room): VoiceRoom
    {
        return $this->rooms->show($room);
    }

    private function tokenFor(VoiceRoom $room, User $user): \App\Features\VoiceRoom\DTOs\VoiceRtcConnectionData
    {
        if ($room->host_id === $user->getKey()) {
            return $this->agora->generateHostToken($room->agora_channel_name, $room->host_uid);
        }

        $seat = $this->rooms->occupiedSeatForUser($room, (string) $user->getKey());

        if ($seat !== null && $seat->stream_uid !== null) {
            return $this->agora->generateSpeakerToken($room->agora_channel_name, $seat->stream_uid);
        }

        return $this->agora->generateAudienceToken($room->agora_channel_name, (string) $user->getKey());
    }
}
