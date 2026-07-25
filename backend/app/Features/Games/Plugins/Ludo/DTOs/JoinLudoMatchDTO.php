<?php

namespace App\Features\Games\Plugins\Ludo\DTOs;

final class JoinLudoMatchDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $matchId,
        public readonly ?string $inviteCode = null,
        public readonly bool $spectator = false,
        public readonly array $metadata = [],
    ) {}
}
