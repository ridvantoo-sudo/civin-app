<?php

namespace App\Features\VoiceRoom\Actions;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\DTOs\CreateVoiceRoomData;
use App\Features\VoiceRoom\DTOs\VoiceRoomConnectionData;
use App\Features\VoiceRoom\Services\VoiceRoomService;

final readonly class CreateVoiceRoom
{
    public function __construct(private VoiceRoomService $voice) {}

    public function execute(User $host, CreateVoiceRoomData $data): VoiceRoomConnectionData
    {
        return $this->voice->create($host, $data);
    }
}
