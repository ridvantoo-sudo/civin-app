<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class RollDiceDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $matchId,
    ) {}
}
