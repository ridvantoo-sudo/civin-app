<?php

namespace App\Features\Ai\DTOs;

final readonly class ModerateVoiceData
{
    public function __construct(
        public string $surface = 'voice',
        public ?string $audioUrl = null,
        public ?int $durationMs = null,
        public ?string $roomId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
    ) {}
}
