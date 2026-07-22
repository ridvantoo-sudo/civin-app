<?php

namespace App\Features\VoiceRoom\Repositories\Contracts;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\DTOs\CreateVoiceRoomData;
use App\Features\VoiceRoom\DTOs\SeatActionData;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSeat;

interface VoiceRoomRepository
{
    public function create(
        User $host,
        CreateVoiceRoomData $data,
        string $roomId,
        string $channel,
        int $hostUid,
    ): VoiceRoom;

    /** @return array{room: VoiceRoom, changed: bool} */
    public function join(VoiceRoom $room, User $user): array;

    /** @return array{room: VoiceRoom, changed: bool, freed_seat: ?VoiceSeat, was_speaker: bool} */
    public function leave(VoiceRoom $room, User $user): array;

    public function requestSeat(VoiceRoom $room, User $user, SeatActionData $data): VoiceSeat;

    public function approveSeat(VoiceRoom $room, SeatActionData $data): VoiceSeat;

    public function rejectSeat(VoiceRoom $room, SeatActionData $data): VoiceSeat;

    /** @return array{room: VoiceRoom, seat: VoiceSeat, user_id: string} */
    public function removeSpeaker(VoiceRoom $room, SeatActionData $data): array;

    public function muteSpeaker(VoiceRoom $room, SeatActionData $data): VoiceSeat;

    public function end(VoiceRoom $room): VoiceRoom;

    public function show(VoiceRoom $room): VoiceRoom;

    public function hostUidExists(int $uid): bool;

    public function streamUidExists(int $uid): bool;

    public function occupiedSeatForUser(VoiceRoom $room, string $userId): ?VoiceSeat;
}
