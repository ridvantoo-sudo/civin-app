<?php

namespace App\Features\Games\Plugins\Quiz\DTOs;

final readonly class StartQuizDTO
{
    public function __construct(
        public string $idempotencyKey,
        public string $sessionId,
    ) {}
}
