<?php

namespace App\Features\Kyc\DTOs;

final readonly class ReviewKycData
{
    public function __construct(
        public string $status,
        public ?string $notes = null,
        public ?string $faceMatchStatus = null,
        public ?float $faceMatchScore = null,
        public bool $withdrawalVerified = false,
    ) {}
}
