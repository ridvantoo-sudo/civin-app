<?php

namespace App\Features\Svip\Actions;

use App\Features\Svip\Models\UserSvip;
use App\Features\Svip\Services\SvipService;
use App\Features\Users\Models\User;

final readonly class GetMySvip
{
    public function __construct(private SvipService $svips) {}

    public function execute(User $user): ?UserSvip
    {
        return $this->svips->me($user);
    }
}
