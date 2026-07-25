<?php

namespace App\Features\Ai\DTOs;

final readonly class SuggestHighlightsData
{
    public function __construct(
        public string $roomId,
        public ?string $replayId = null,
    ) {}
}
