<?php

namespace App\Features\CoinSeller\Actions;

use App\Features\CoinSeller\Services\CoinSellerService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCoinSellersForAdmin
{
    public function __construct(private CoinSellerService $sellers) {}

    public function execute(User $admin, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->sellers->listForAdmin($admin, $status, $perPage);
    }
}
