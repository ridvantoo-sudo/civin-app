<?php

namespace App\Features\Games\Plugins\LuckyDice\DTOs;

final readonly class LuckyDiceDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $betMode,
        public string $prediction,
        public int $betAmount,
        public ?string $clientSessionId = null,
        public array $metadata = [],
    ) {}
}
