<?php

namespace App\Features\UserStatus\DTOs;

final readonly class UpdateUserStatusData
{
    public function __construct(
        public ?bool $isOnline,
        public ?bool $isLive,
    ) {}
}
