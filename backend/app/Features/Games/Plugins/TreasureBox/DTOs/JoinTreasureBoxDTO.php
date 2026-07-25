<?php

namespace App\Features\Games\Plugins\TreasureBox\DTOs;

final readonly class JoinTreasureBoxDTO
{
    /** @param  array<string, mixed>  $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $treasureBoxId,
        public array $metadata = [],
    ) {}
}
