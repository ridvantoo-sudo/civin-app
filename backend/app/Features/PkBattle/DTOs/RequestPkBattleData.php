<?php

namespace App\Features\PkBattle\DTOs;

final readonly class RequestPkBattleData
{
    public function __construct(
        public string $opponentRoomId,
        public int $durationSeconds,
    ) {}
}
