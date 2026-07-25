<?php

namespace App\Features\Games\Plugins\SlotMachine\DTOs;

final readonly class SlotMachineDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public string $gameMode,
        public int $betAmount,
        public int $spinCount = 1,
        public bool $autoSpin = false,
        public array $metadata = [],
        public ?string $clientSessionId = null,
    ) {}
}
