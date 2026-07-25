<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class InviteMemberData
{
    public function __construct(
        public ?string $userId = null,
        public string $role = 'member',
        public ?int $expiresInHours = 72,
    ) {}
}
