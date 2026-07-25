<?php

namespace App\Features\Games\Plugins\RockPaperScissors\DTOs;

final readonly class AcceptRpsMatchDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $matchId,
        public array $metadata = [],
    ) {}
}
