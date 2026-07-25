<?php

namespace App\Features\Games\DTOs;

final readonly class PlayGameData
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $gameSlug,
        public int $betCoins = 0,
        public array $input = [],
        public ?array $metadata = null,
    ) {}
}
