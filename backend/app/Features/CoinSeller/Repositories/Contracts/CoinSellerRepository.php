<?php

namespace App\Features\CoinSeller\Repositories\Contracts;

use App\Features\CoinSeller\DTOs\AllocateInventoryData;
use App\Features\CoinSeller\DTOs\ApplyCoinSellerData;
use App\Features\CoinSeller\DTOs\CreateRechargeCodeData;
use App\Features\CoinSeller\DTOs\DirectSellData;
use App\Features\CoinSeller\DTOs\RedeemRechargeCodeData;
use App\Features\CoinSeller\DTOs\ReviewCoinSellerData;
use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CoinSellerRepository
{
    public function findByUser(User $user): ?CoinSeller;

    public function find(string $id): ?CoinSeller;

    public function apply(User $user, ApplyCoinSellerData $data): CoinSeller;

    public function review(CoinSeller $seller, User $reviewer, ReviewCoinSellerData $data): CoinSeller;

    public function allocateInventory(CoinSeller $seller, User $actor, AllocateInventoryData $data): CoinSeller;

    public function directSell(CoinSeller $seller, User $sellerUser, DirectSellData $data): CoinSellerSale;

    public function createRechargeCode(CoinSeller $seller, User $sellerUser, CreateRechargeCodeData $data): CoinRechargeCode;

    public function revokeRechargeCode(CoinRechargeCode $code, User $actor): CoinRechargeCode;

    public function redeemRechargeCode(User $buyer, RedeemRechargeCodeData $data): CoinSellerSale;

    public function salesForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator;

    public function codesForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator;

    public function inventoryLogsForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator;

    public function commissionsForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator;

    public function listForAdmin(?string $status, int $perPage): LengthAwarePaginator;

    /** @return array{inventory_balance: int, total_sold: int, total_commission: int, sales_count: int, available_codes: int, pending_codes: int} */
    public function dashboardStats(CoinSeller $seller): array;
}
