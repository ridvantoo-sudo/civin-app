<?php

namespace App\Features\Authentication\DTOs;

use DateTimeInterface;

final readonly class FirebaseIdentity
{
    public function __construct(
        public string $uid,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $avatar = null,
        public bool $emailVerified = false,
        public ?DateTimeInterface $expiresAt = null,
    ) {}
}
