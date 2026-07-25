<?php

namespace App\Features\Ads\DTOs;

final readonly class CreateAdCampaignData
{
    /**
     * @param  array<string, mixed>|null  $targetPayload
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $title,
        public string $placement,
        public ?string $body = null,
        public ?string $mediaUrl = null,
        public string $mediaType = 'image',
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public string $targetType = 'all',
        public ?array $targetPayload = null,
        public string $status = 'draft',
        public int $priority = 0,
        public ?int $budgetImpressions = null,
        public ?int $budgetClicks = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?array $metadata = null,
    ) {}
}
