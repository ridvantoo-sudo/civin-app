<?php

namespace App\Features\Vip\Actions;

use App\Features\Users\Models\User;
use App\Features\Vip\DTOs\UpgradeVipData;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Services\VipService;

final readonly class UpgradeVip
{
    public function __construct(private VipService $vips) {}

    public function execute(User $user, UpgradeVipData $data): UserVip
    {
        return $this->vips->upgrade($user, $data);
    }
}
