<?php

namespace App\Features\Ads\Services;

use App\Features\Levels\Models\UserLevelProgress;
use App\Features\Users\Models\User;

final class AdEligibilityService
{
    public function minCreatorLevel(): int
    {
        return max(1, (int) config('ads.min_creator_level', 10));
    }

    public function allowSvip(): bool
    {
        return (bool) config('ads.allow_svip', true);
    }

    public function userLevel(User $user): int
    {
        $progress = UserLevelProgress::query()
            ->where('user_id', $user->getKey())
            ->value('level');

        if ($progress !== null) {
            return (int) $progress;
        }

        $profileLevel = $user->profile?->level;

        return max(1, (int) ($profileLevel ?? 1));
    }

    public function isSvip(User $user): bool
    {
        return (bool) ($user->profile?->is_svip);
    }

    public function canCreateAds(User $user): bool
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            return false;
        }

        if ($this->allowSvip() && $this->isSvip($user)) {
            return true;
        }

        return $this->userLevel($user) >= $this->minCreatorLevel();
    }

    /** @return array{eligible: bool, min_level: int, user_level: int, is_svip: bool, allow_svip: bool, reason: string|null} */
    public function eligibilityPayload(User $user): array
    {
        $minLevel = $this->minCreatorLevel();
        $userLevel = $this->userLevel($user);
        $isSvip = $this->isSvip($user);
        $allowSvip = $this->allowSvip();
        $eligible = $this->canCreateAds($user);

        $reason = null;
        if (! $eligible) {
            if ($user->is_guest || $user->status !== User::STATUS_ACTIVE) {
                $reason = 'Only active registered users can create ads.';
            } elseif ($allowSvip) {
                $reason = sprintf(
                    'Reach Level %d or upgrade to SVIP to unlock ad creation.',
                    $minLevel,
                );
            } else {
                $reason = sprintf('Reach Level %d to unlock ad creation.', $minLevel);
            }
        }

        return [
            'eligible' => $eligible,
            'min_level' => $minLevel,
            'user_level' => $userLevel,
            'is_svip' => $isSvip,
            'allow_svip' => $allowSvip,
            'reason' => $reason,
        ];
    }
}
