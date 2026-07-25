<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\DTOs\DirectSellData;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class DirectSellCoins
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user, DirectSellData $data): CoinSellerSale
    {
        return $this->sellers->directSell($user, $data);
    }
}
