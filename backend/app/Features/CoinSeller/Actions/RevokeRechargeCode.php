<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class RevokeRechargeCode
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user, CoinRechargeCode $code): CoinRechargeCode
    {
        return $this->sellers->revokeRechargeCode($user, $code);
    }
}
