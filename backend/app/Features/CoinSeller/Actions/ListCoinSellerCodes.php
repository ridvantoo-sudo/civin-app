<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCoinSellerCodes
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->sellers->codes($user, $perPage);
    }
}
