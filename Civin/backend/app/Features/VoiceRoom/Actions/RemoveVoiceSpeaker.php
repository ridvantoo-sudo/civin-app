<?php

namespace App\Features\VoiceRoom\Actions;

use App\Features\VoiceRoom\DTOs\SeatActionData;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Services\VoiceRoomService;

final readonly class RemoveVoiceSpeaker
{
    public function __construct(private VoiceRoomService $voice) {}

    public function execute(VoiceRoom $room, SeatActionData $data): VoiceRoom
    {
        return $this->voice->removeSpeaker($room, $data);
    }
}
