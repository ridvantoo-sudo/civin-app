<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class GetAdEligibility
{
    public function __construct(private AdService $ads) {}

    /** @return array{eligible: bool, min_level: int, user_level: int, is_svip: bool, allow_svip: bool, reason: string|null} */
    public function execute(User $user): array
    {
        return $this->ads->eligibility($user);
    }
}
