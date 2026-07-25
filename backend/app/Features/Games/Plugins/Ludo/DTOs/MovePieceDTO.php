<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class MovePieceDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $matchId,
        public readonly int $pieceIndex,
        public readonly array $metadata = [],
    ) {}
}
