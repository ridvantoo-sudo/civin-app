<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\DTOs\RedeemRechargeCodeData;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class RedeemRechargeCode
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user, RedeemRechargeCodeData $data): CoinSellerSale
    {
        return $this->sellers->redeemRechargeCode($user, $data);
    }
}
