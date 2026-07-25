<?php

namespace App\Features\Kyc\DTOs;

final readonly class SubmitKycData
{
    public function __construct(
        public string $documentType,
        public string $documentNumber,
        public string $fullLegalName,
        public string $documentFrontPath,
        public string $selfiePath,
        public ?string $documentBackPath = null,
        public ?string $nationality = null,
        public ?string $dateOfBirth = null,
    ) {}
}
