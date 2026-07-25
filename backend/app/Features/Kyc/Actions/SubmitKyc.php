<?php

namespace App\Features\Kyc\Actions;

use App\Features\Kyc\DTOs\SubmitKycData;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Services\KycService;
use App\Features\Users\Models\User;

final readonly class SubmitKyc
{
    public function __construct(private KycService $kyc) {}

    public function execute(User $user, SubmitKycData $data): KycSubmission
    {
        return $this->kyc->submit($user, $data);
    }
}
