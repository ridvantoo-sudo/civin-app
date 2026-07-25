<?php

namespace App\Features\Svip\Actions;

use App\Features\Svip\DTOs\PurchaseSvipData;
use App\Features\Svip\Models\UserSvip;
use App\Features\Svip\Services\SvipService;
use App\Features\Users\Models\User;

final readonly class PurchaseSvip
{
    public function __construct(private SvipService $svips) {}

    public function execute(User $user, PurchaseSvipData $data): UserSvip
    {
        return $this->svips->purchase($user, $data);
    }
}
