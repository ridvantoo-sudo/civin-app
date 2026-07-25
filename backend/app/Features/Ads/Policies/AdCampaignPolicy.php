<?php

namespace App\Features\Ads\Policies;

use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Services\AdEligibilityService;
use App\Features\Users\Models\User;

final class AdCampaignPolicy
{
    public function __construct(private AdEligibilityService $eligibility) {}

    public function viewAny(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE;
    }

    public function view(User $user, AdCampaign $campaign): bool
    {
        if ($user->is_admin || $campaign->isOwnedBy($user)) {
            return true;
        }

        return $campaign->isRunnableNow();
    }

    public function create(User $user): bool
    {
        return $this->eligibility->canCreateAds($user);
    }

    public function update(User $user, AdCampaign $campaign): bool
    {
        return $this->eligibility->canCreateAds($user) && $campaign->isOwnedBy($user);
    }

    public function delete(User $user, AdCampaign $campaign): bool
    {
        return $this->eligibility->canCreateAds($user) && $campaign->isOwnedBy($user);
    }

    public function manage(User $user): bool
    {
        return $this->eligibility->canCreateAds($user);
    }

    public function track(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE;
    }
}
