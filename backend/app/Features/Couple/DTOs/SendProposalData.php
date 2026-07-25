<?php

namespace App\Features\Couple\DTOs;

final readonly class SendProposalData
{
    public function __construct(
        public string $userId,
        public ?string $message = null,
        public ?int $expiresInHours = null,
    ) {}
}
