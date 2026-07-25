<?php

namespace App\Features\Svip\Repositories\Contracts;

use App\Features\Svip\DTOs\PurchaseSvipData;
use App\Features\Svip\DTOs\UpgradeSvipData;
use App\Features\Svip\Models\SvipLevel;
use App\Features\Svip\Models\UserSvip;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

interface SvipRepository
{
    public function activeLevels(): Collection;

    public function findActiveLevel(string $svipLevelId): ?SvipLevel;

    public function findForUser(User $user): ?UserSvip;

    public function lockForUser(User $user): ?UserSvip;

    public function purchase(User $user, SvipLevel $level, PurchaseSvipData $data): UserSvip;

    public function upgrade(User $user, UserSvip $subscription, SvipLevel $level, UpgradeSvipData $data): UserSvip;

    /** @return list<UserSvip> */
    public function expiredActiveSubscriptions(): Collection;

    public function expire(UserSvip $subscription): UserSvip;
}
