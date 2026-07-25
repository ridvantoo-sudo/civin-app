<?php

namespace App\Features\FamilyClub\DTOs;

final readonly class SendFamilyMessageData
{
    public function __construct(
        public string $body,
    ) {}
}
