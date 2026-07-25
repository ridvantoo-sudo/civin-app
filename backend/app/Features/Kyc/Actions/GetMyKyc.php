<?php

namespace App\Features\Kyc\Actions;

use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Services\KycService;
use App\Features\Users\Models\User;

final readonly class GetMyKyc
{
    public function __construct(private KycService $kyc) {}

    public function execute(User $user): ?KycSubmission
    {
        return $this->kyc->me($user);
    }
}
