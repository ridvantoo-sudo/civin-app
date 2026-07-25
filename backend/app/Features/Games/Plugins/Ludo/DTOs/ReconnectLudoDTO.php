<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class ReconnectLudoDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $matchId,
    ) {}
}
