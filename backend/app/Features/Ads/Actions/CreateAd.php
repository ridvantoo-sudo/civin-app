<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\DTOs\CreateAdCampaignData;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class CreateAd
{
    public function __construct(private AdService $ads) {}

    public function execute(User $user, CreateAdCampaignData $data): AdCampaign
    {
        return $this->ads->create($user, $data);
    }
}
