<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class GetMyCoinSeller
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user): ?CoinSeller
    {
        return $this->sellers->me($user);
    }
}
