<?php

namespace App\Features\Games\Plugins\RockPaperScissors\DTOs;

final readonly class JoinRpsTournamentDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $tournamentId,
        public array $metadata = [],
    ) {}
}
