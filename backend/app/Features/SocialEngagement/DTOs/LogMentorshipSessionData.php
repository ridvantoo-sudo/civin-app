<?php

namespace App\Features\SocialEngagement\DTOs;

final readonly class LogMentorshipSessionData
{
    public function __construct(
        public int $durationMinutes,
        public ?string $notes = null,
        public ?\DateTimeInterface $heldAt = null,
    ) {}
}
