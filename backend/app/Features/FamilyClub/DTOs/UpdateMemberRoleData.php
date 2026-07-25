<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class UpdateMemberRoleData
{
    public function __construct(
        public string $userId,
        public string $role,
    ) {}
}
