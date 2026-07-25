<?php

namespace App\Features\Games\DTOs;

final readonly class TrackAnalyticsData
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $event,
        public ?string $gameSlug = null,
        public ?array $payload = null,
    ) {}
}
