<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class ReviewMemberData
{
    public function __construct(
        public string $userId,
    ) {}
}
