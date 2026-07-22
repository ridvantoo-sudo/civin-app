<?php

namespace App\Features\Vip\Actions;

use App\Features\Users\Models\User;
use App\Features\Vip\DTOs\VipPrivilegesData;
use App\Features\Vip\Services\VipService;

final readonly class CheckVipPrivileges
{
    public function __construct(private VipService $vips) {}

    public function execute(User $user): ?VipPrivilegesData
    {
        return $this->vips->privileges($user);
    }

    public function has(User $user, string $privilege): bool
    {
        return $this->vips->hasPrivilege($user, $privilege);
    }
}
