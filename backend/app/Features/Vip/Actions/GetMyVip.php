<?php

namespace App\Features\Vip\Actions;

use App\Features\Users\Models\User;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Services\VipService;

final readonly class GetMyVip
{
    public function __construct(private VipService $vips) {}

    public function execute(User $user): ?UserVip
    {
        return $this->vips->me($user);
    }
}
