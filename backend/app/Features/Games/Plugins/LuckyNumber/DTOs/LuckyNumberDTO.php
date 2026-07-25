<?php

namespace App\Features\Games\Plugins\LuckyNumber\DTOs;

final readonly class LuckyNumberDTO
{
    /**
     * @param  list<int>  $pickedNumbers
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $gameMode,
        public int $betAmount,
        public array $pickedNumbers = [],
        public bool $autoPick = false,
        public bool $repeatTicket = false,
        public array $metadata = [],
        public ?string $clientSessionId = null,
    ) {}
}
