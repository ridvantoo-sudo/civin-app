<?php

namespace App\Features\Games\Plugins\LuckyWheel\DTOs;

final readonly class LuckyWheelDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $idempotencyKey,
        public ?int $costCoins = null,
        public array $metadata = [],
        public ?string $clientSessionId = null,
    ) {}
}
