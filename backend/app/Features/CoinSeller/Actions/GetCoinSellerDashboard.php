<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class GetCoinSellerDashboard
{
    public function __construct(private CoinSellerService $sellers) {}

    /** @return array{seller: CoinSeller, stats: array<string, int|float>} */
    public function execute(User $user): array
    {
        return $this->sellers->dashboard($user);
    }
}
