<?php

namespace App\Features\Games\Plugins\RockPaperScissors\DTOs;

final readonly class RematchRpsDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $matchId,
        public ?int $entryFeeCoins = null,
        public array $metadata = [],
    ) {}
}
