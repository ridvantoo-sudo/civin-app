<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class CreateLudoMatchDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $mode = 'classic_4',
        public readonly string $matchmaking = 'private',
        public readonly int $entryFeeCoins = 0,
        public readonly int $maxPlayers = 4,
        public readonly ?string $roomId = null,
        public readonly ?string $roomType = null,
        public readonly ?string $tournamentId = null,
        public readonly array $metadata = [],
    ) {}
}
