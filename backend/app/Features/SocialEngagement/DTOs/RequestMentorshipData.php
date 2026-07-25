<?php

namespace App\Features\SocialEngagement\DTOs;

final readonly class RequestMentorshipData
{
    public function __construct(
        public string $targetUserId,
        public string $role, // mentor|mentee — what requester wants to be
        public ?string $message = null,
    ) {}
}
