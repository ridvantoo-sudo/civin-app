<?php

namespace App\Features\Games\Plugins\Quiz\DTOs;

final readonly class JoinQuizDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $sessionId,
        public ?string $teamId = null,
        public ?string $teamName = null,
        public array $metadata = [],
    ) {}
}
