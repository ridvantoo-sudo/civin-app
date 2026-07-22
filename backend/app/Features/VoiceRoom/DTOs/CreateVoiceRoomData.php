<?php

namespace App\Features\VoiceRoom\DTOs;

final readonly class CreateVoiceRoomData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $thumbnail,
        public int $seatCount,
    ) {}
}
