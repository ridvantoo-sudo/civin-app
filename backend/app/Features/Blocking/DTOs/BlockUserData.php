<?php

namespace App\Features\Blocking\DTOs;

final readonly class BlockUserData
{
    public function __construct(public string $userId) {}
}
