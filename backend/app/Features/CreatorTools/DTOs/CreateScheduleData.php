<?php

namespace App\Features\CreatorTools\DTOs;

final readonly class CreateScheduleData
{
    public function __construct(
        public string $title,
        public \DateTimeInterface $scheduledAt,
        public string $timezone = 'UTC',
        public ?int $categoryId = null,
        public ?string $description = null,
        public ?string $thumbnail = null,
        public ?array $metadata = null,
    ) {}
}
