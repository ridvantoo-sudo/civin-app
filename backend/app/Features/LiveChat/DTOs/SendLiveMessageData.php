<?php

namespace App\Features\LiveChat\DTOs;

final readonly class SendLiveMessageData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $message,
        public string $type = 'TEXT',
        public ?array $metadata = null,
    ) {}
}
