<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class CreateFamilyData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $logo = null,
    ) {}
}
