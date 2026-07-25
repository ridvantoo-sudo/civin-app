<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class ReadyLudoDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $matchId,
        public readonly bool $ready = true,
    ) {}
}
