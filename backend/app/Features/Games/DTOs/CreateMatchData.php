<?php

namespace App\Features\Games\DTOs;

final readonly class CreateMatchData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $gameSlug,
        public int $stakeCoins = 0,
        public int $maxPlayers = 2,
        public ?array $metadata = null,
    ) {}
}
