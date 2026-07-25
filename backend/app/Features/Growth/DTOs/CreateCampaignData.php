<?php

namespace App\Features\Growth\DTOs;

final readonly class CreateCampaignData
{
    /**
     * @param  array<string, mixed>|null  $audience
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $name,
        public string $channel,
        public string $body,
        public ?string $subject = null,
        public ?string $actionUrl = null,
        public ?array $audience = null,
        public ?\DateTimeInterface $scheduledAt = null,
        public ?array $metadata = null,
    ) {}
}
