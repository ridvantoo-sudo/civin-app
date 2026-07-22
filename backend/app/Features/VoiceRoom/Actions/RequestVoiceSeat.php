<?php

namespace App\Features\VoiceRoom\Actions;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\DTOs\SeatActionData;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Services\VoiceRoomService;

final readonly class RequestVoiceSeat
{
    public function __construct(private VoiceRoomService $voice) {}

    public function execute(VoiceRoom $room, User $user, SeatActionData $data): VoiceRoom
    {
        return $this->voice->requestSeat($room, $user, $data);
    }
}
