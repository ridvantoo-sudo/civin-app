<?php

namespace App\Features\Kyc\Actions;

use App\Features\Kyc\DTOs\ReviewKycData;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Services\KycService;
use App\Features\Users\Models\User;

final readonly class ReviewKyc
{
    public function __construct(private KycService $kyc) {}

    public function execute(KycSubmission $submission, User $reviewer, ReviewKycData $data): KycSubmission
    {
        return $this->kyc->review($submission, $reviewer, $data);
    }
}
