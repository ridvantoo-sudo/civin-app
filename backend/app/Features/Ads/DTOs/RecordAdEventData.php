<?php

namespace App\Features\Ads\DTOs;

final readonly class RecordAdEventData
{
    /** @param  array<string, mixed>|null  $metadata */
    public function __construct(
        public string $eventType,
        public ?string $contextId = null,
        public ?array $metadata = null,
    ) {}
}
