<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\DTOs\ReviewCoinSellerData;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class ReviewCoinSeller
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(CoinSeller $seller, User $reviewer, ReviewCoinSellerData $data): CoinSeller
    {
        return $this->sellers->review($seller, $reviewer, $data);
    }
}
