<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\DTOs\CreateRechargeCodeData;
use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;

final readonly class CreateRechargeCode
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user, CreateRechargeCodeData $data): CoinRechargeCode
    {
        return $this->sellers->createRechargeCode($user, $data);
    }
}
