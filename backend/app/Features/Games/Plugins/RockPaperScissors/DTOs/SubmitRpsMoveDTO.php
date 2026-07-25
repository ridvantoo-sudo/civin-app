<?php

namespace App\Features\Games\Plugins\RockPaperScissors\DTOs;

final readonly class SubmitRpsMoveDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $matchId,
        public string $move,
        public ?string $roundId = null,
        public ?int $clientResponseMs = null,
        public array $metadata = [],
    ) {}
}
