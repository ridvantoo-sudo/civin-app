<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class JoinLudoTournamentDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $tournamentId,
        public readonly array $metadata = [],
    ) {}
}
