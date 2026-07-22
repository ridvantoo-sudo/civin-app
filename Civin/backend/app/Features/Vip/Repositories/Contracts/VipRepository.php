<?php

namespace App\Features\Vip\Repositories\Contracts;

use App\Features\Users\Models\User;
use App\Features\Vip\DTOs\PurchaseVipData;
use App\Features\Vip\DTOs\UpgradeVipData;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Models\VipLevel;
use Illuminate\Support\Collection;

interface VipRepository
{
    public function activeLevels(): Collection;

    public function findActiveLevel(string $vipLevelId): ?VipLevel;

    public function findForUser(User $user): ?UserVip;

    public function lockForUser(User $user): ?UserVip;

    public function purchase(User $user, VipLevel $level, PurchaseVipData $data): UserVip;

    public function upgrade(User $user, UserVip $subscription, VipLevel $level, UpgradeVipData $data): UserVip;

    /** @return list<UserVip> */
    public function expiredActiveSubscriptions(): Collection;

    public function expire(UserVip $subscription): UserVip;
}
