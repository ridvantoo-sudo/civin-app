<?php

namespace App\Features\Games\Plugins\Quiz\DTOs;

final readonly class SubmitAnswerDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $idempotencyKey,
        public string $sessionId,
        public string $questionId,
        public ?int $selectedIndex = null,
        public ?string $selectedText = null,
        public ?int $clientResponseMs = null,
        public array $metadata = [],
    ) {}
}
