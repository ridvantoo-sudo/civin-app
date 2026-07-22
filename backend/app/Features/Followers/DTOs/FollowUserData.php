<?php

namespace App\Features\Followers\DTOs;

final readonly class FollowUserData
{
    public function __construct(public string $userId) {}
}
