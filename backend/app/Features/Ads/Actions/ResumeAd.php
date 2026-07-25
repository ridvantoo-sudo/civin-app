<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class ResumeAd
{
    public function __construct(private AdService $ads) {}

    public function execute(User $user, string $id): AdCampaign
    {
        return $this->ads->resume($user, $id);
    }
}
