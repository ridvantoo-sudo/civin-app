<?php

namespace App\Features\UserSearch\DTOs;

final readonly class UserSearchCriteria
{
    public function __construct(
        public ?string $query,
        public ?string $country,
        public ?bool $isOnline,
        public int $perPage,
    ) {}
}
