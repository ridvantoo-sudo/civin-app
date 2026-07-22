<?php

namespace App\Features\Vip\Actions;

use App\Features\Users\Models\User;
use App\Features\Vip\DTOs\PurchaseVipData;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Services\VipService;

final readonly class PurchaseVip
{
    public function __construct(private VipService $vips) {}

    public function execute(User $user, PurchaseVipData $data): UserVip
    {
        return $this->vips->purchase($user, $data);
    }
}
