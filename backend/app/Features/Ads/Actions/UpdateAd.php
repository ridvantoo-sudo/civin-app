<?php

namespace App\Features\Ads\Actions;

use App\Features\Ads\DTOs\UpdateAdCampaignData;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Services\AdService;
use App\Features\Users\Models\User;

final readonly class UpdateAd
{
    public function __construct(private AdService $ads) {}

    public function execute(User $user, string $id, UpdateAdCampaignData $data): AdCampaign
    {
        return $this->ads->update($user, $id, $data);
    }
}
