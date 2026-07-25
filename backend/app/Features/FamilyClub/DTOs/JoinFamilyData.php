<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class JoinFamilyData
{
    public function __construct(
        public string $code,
    ) {}
}
