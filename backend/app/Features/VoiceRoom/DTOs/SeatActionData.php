<?php

namespace App\Features\VoiceRoom\DTOs;

final readonly class SeatActionData
{
    public function __construct(
        public int $seatIndex,
        public ?bool $muted = null,
    ) {}
}
