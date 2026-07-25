<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\DTOs\ApplyCoinSellerData;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class ApplyAsCoinSeller
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user, ApplyCoinSellerData $data): CoinSeller
    {
        return $this->sellers->apply($user, $data);
    }
}
