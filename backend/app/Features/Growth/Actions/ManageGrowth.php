<?php

namespace App\Features\Growth\Actions;

use App\Features\Growth\DTOs\AttachReferralData;
use App\Features\Growth\DTOs\CreateCampaignData;
use App\Features\Growth\DTOs\CreateInviteData;
use App\Features\Growth\DTOs\CreatePromoCodeData;
use App\Features\Growth\DTOs\RedeemPromoData;
use App\Features\Growth\Models\GrowthCampaign;
use App\Features\Growth\Models\Invite;
use App\Features\Growth\Models\PromoCode;
use App\Features\Growth\Models\PromoRedemption;
use App\Features\Growth\Models\ReferralAttribution;
use App\Features\Growth\Models\ReferralCode;
use App\Features\Growth\Services\CampaignService;
use App\Features\Growth\Services\InviteService;
use App\Features\Growth\Services\PromoCodeService;
use App\Features\Growth\Services\ReferralService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ManageGrowth
{
    public function __construct(
        private ReferralService $referrals,
        private InviteService $invites,
        private PromoCodeService $promos,
        private CampaignService $campaigns,
    ) {}

    public function myReferralCode(User $user): ReferralCode
    {
        return $this->referrals->myCode($user);
    }

    public function referralStats(User $user): array
    {
        return $this->referrals->stats($user);
    }

    public function attachReferral(User $user, AttachReferralData $data): ReferralAttribution
    {
        return $this->referrals->attach($user, $data);
    }

    public function createInvite(User $user, CreateInviteData $data): Invite
    {
        return $this->invites->create($user, $data);
    }

    public function acceptInvite(User $user, string $token): Invite
    {
        return $this->invites->accept($user, $token);
    }

    public function myInvites(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->invites->mine($user, $perPage);
    }

    public function createPromo(User $user, CreatePromoCodeData $data): PromoCode
    {
        return $this->promos->create($user, $data);
    }

    public function redeemPromo(User $user, RedeemPromoData $data): PromoRedemption
    {
        return $this->promos->redeem($user, $data);
    }

    public function validatePromo(string $code): PromoCode
    {
        return $this->promos->validate($code);
    }

    public function myPromoRedemptions(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->promos->mine($user, $perPage);
    }

    public function createCampaign(User $user, CreateCampaignData $data): GrowthCampaign
    {
        return $this->campaigns->create($user, $data);
    }

    public function dispatchCampaign(User $user, GrowthCampaign $campaign): GrowthCampaign
    {
        return $this->campaigns->dispatchNow($user, $campaign);
    }

    public function cancelCampaign(User $user, GrowthCampaign $campaign): GrowthCampaign
    {
        return $this->campaigns->cancel($user, $campaign);
    }

    public function myCampaigns(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->campaigns->mine($user, $perPage);
    }
}
