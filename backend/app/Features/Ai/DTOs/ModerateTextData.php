<?php

namespace App\Features\Ai\DTOs;

final readonly class ModerateTextData
{
    public function __construct(
        public string $text,
        public string $surface = 'chat',
        public ?string $subjectType = null,
        public ?string $subjectId = null,
    ) {}
}
