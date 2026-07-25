<?php

namespace App\Features\Growth\Policies;

use App\Features\Growth\Models\GrowthCampaign;
use App\Features\Growth\Models\Invite;
use App\Features\Growth\Models\PromoCode;
use App\Features\Growth\Models\ReferralCode;
use App\Features\Users\Models\User;

final class GrowthPolicy
{
    public function use(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function viewReferral(User $user, ReferralCode $code): bool
    {
        return $this->use($user) && $code->user_id === $user->getKey();
    }

    public function viewInvite(User $user, Invite $invite): bool
    {
        return $this->use($user) && $invite->inviter_id === $user->getKey();
    }

    public function managePromo(User $user): bool
    {
        return $this->use($user);
    }

    public function viewCampaign(User $user, GrowthCampaign $campaign): bool
    {
        return $this->use($user) && $campaign->created_by === $user->getKey();
    }

    public function manageCampaign(User $user, GrowthCampaign $campaign): bool
    {
        return $this->viewCampaign($user, $campaign);
    }
}
