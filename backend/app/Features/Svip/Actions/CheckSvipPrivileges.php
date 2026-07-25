<?php

namespace App\Features\Svip\Actions;

use App\Features\Svip\DTOs\SvipPrivilegesData;
use App\Features\Svip\Services\SvipService;
use App\Features\Users\Models\User;

final readonly class CheckSvipPrivileges
{
    public function __construct(private SvipService $svips) {}

    public function execute(User $user): ?SvipPrivilegesData
    {
        return $this->svips->privileges($user);
    }

    public function has(User $user, string $privilege): bool
    {
        return $this->svips->hasPrivilege($user, $privilege);
    }
}
