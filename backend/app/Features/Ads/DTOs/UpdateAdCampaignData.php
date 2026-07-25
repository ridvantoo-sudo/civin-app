<?php

namespace App\Features\Ads\DTOs;

final readonly class UpdateAdCampaignData
{
    /**
     * @param  array<string, mixed>|null  $targetPayload
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $title = null,
        public ?string $body = null,
        public ?string $placement = null,
        public ?string $mediaUrl = null,
        public ?string $mediaType = null,
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $targetType = null,
        public ?array $targetPayload = null,
        public ?int $priority = null,
        public ?int $budgetImpressions = null,
        public ?int $budgetClicks = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?array $metadata = null,
        public bool $clearStartsAt = false,
        public bool $clearEndsAt = false,
    ) {}
}
