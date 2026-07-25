<?php

namespace App\Features\CoinSeller\Repositories\Eloquent;

use App\Features\CoinSeller\DTOs\AllocateInventoryData;
use App\Features\CoinSeller\DTOs\ApplyCoinSellerData;
use App\Features\CoinSeller\DTOs\CreateRechargeCodeData;
use App\Features\CoinSeller\DTOs\DirectSellData;
use App\Features\CoinSeller\DTOs\RedeemRechargeCodeData;
use App\Features\CoinSeller\DTOs\ReviewCoinSellerData;
use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Models\CoinSellerAuditLog;
use App\Features\CoinSeller\Models\CoinSellerCommission;
use App\Features\CoinSeller\Models\CoinSellerInventoryLog;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\CoinSeller\Repositories\Contracts\CoinSellerRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EloquentCoinSellerRepository implements CoinSellerRepository
{
    public function __construct(private WalletRepository $wallets) {}

    public function findByUser(User $user): ?CoinSeller
    {
        return CoinSeller::query()
            ->where('user_id', $user->getKey())
            ->with(['user.profile', 'user.socialStatus', 'reviewer'])
            ->first();
    }

    public function find(string $id): ?CoinSeller
    {
        return CoinSeller::query()
            ->with(['user.profile', 'user.socialStatus', 'reviewer'])
            ->find($id);
    }

    public function apply(User $user, ApplyCoinSellerData $data): CoinSeller
    {
        return DB::transaction(function () use ($user, $data): CoinSeller {
            $existing = CoinSeller::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (in_array($existing->status, [CoinSeller::STATUS_PENDING, CoinSeller::STATUS_ACTIVE], true)) {
                    throw ValidationException::withMessages([
                        'seller' => 'You already have a coin seller application or account.',
                    ]);
                }

                $existing->forceFill([
                    'display_name' => $data->displayName,
                    'contact_info' => $data->contactInfo,
                    'application_message' => $data->applicationMessage,
                    'status' => CoinSeller::STATUS_PENDING,
                    'reviewed_by' => null,
                    'admin_notes' => null,
                    'reviewed_at' => null,
                ])->save();

                $this->writeAudit($existing, $user->getKey(), CoinSellerAuditLog::ACTION_APPLIED, [
                    'display_name' => $data->displayName,
                    'reapplied' => true,
                ]);

                return $existing->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
            }

            $seller = CoinSeller::query()->create([
                'user_id' => $user->getKey(),
                'display_name' => $data->displayName,
                'contact_info' => $data->contactInfo,
                'application_message' => $data->applicationMessage,
                'status' => CoinSeller::STATUS_PENDING,
                'commission_rate' => 5,
                'inventory_balance' => 0,
                'total_sold' => 0,
                'total_commission' => 0,
                'sales_count' => 0,
            ]);

            $this->writeAudit($seller, $user->getKey(), CoinSellerAuditLog::ACTION_APPLIED, [
                'display_name' => $data->displayName,
            ]);

            return $seller->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
        });
    }

    public function review(CoinSeller $seller, User $reviewer, ReviewCoinSellerData $data): CoinSeller
    {
        return DB::transaction(function () use ($seller, $reviewer, $data): CoinSeller {
            $locked = CoinSeller::query()->lockForUpdate()->findOrFail($seller->getKey());

            $allowed = match ($data->status) {
                CoinSeller::STATUS_ACTIVE => [CoinSeller::STATUS_PENDING, CoinSeller::STATUS_SUSPENDED, CoinSeller::STATUS_REJECTED],
                CoinSeller::STATUS_REJECTED => [CoinSeller::STATUS_PENDING],
                CoinSeller::STATUS_SUSPENDED => [CoinSeller::STATUS_ACTIVE],
                default => [],
            };

            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => 'This seller account cannot transition to the requested status.',
                ]);
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => $data->status,
                'commission_rate' => $data->commissionRate ?? $locked->commission_rate,
                'reviewed_by' => $reviewer->getKey(),
                'admin_notes' => $data->notes,
                'reviewed_at' => now(),
            ])->save();

            $action = match ($data->status) {
                CoinSeller::STATUS_ACTIVE => $previousStatus === CoinSeller::STATUS_SUSPENDED
                    ? CoinSellerAuditLog::ACTION_REACTIVATED
                    : CoinSellerAuditLog::ACTION_APPROVED,
                CoinSeller::STATUS_REJECTED => CoinSellerAuditLog::ACTION_REJECTED,
                CoinSeller::STATUS_SUSPENDED => CoinSellerAuditLog::ACTION_SUSPENDED,
                default => CoinSellerAuditLog::ACTION_APPROVED,
            };

            $this->writeAudit($locked, $reviewer->getKey(), $action, array_filter([
                'status' => $data->status,
                'commission_rate' => $data->commissionRate,
                'notes' => $data->notes,
            ], fn ($value) => $value !== null));

            return $locked->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
        });
    }

    public function allocateInventory(CoinSeller $seller, User $actor, AllocateInventoryData $data): CoinSeller
    {
        return DB::transaction(function () use ($seller, $actor, $data): CoinSeller {
            $locked = CoinSeller::query()->lockForUpdate()->findOrFail($seller->getKey());

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'seller' => 'Inventory can only be allocated to active sellers.',
                ]);
            }

            if ($data->coins < 1) {
                throw ValidationException::withMessages([
                    'coins' => 'Allocation must be at least 1 coin.',
                ]);
            }

            $locked->increment('inventory_balance', $data->coins);
            $locked->refresh();

            CoinSellerInventoryLog::query()->create([
                'coin_seller_id' => $locked->getKey(),
                'actor_id' => $actor->getKey(),
                'type' => CoinSellerInventoryLog::TYPE_ADMIN_ALLOCATE,
                'amount' => $data->coins,
                'balance_after' => $locked->inventory_balance,
                'notes' => $data->notes,
                'created_at' => now(),
            ]);

            $this->writeAudit($locked, $actor->getKey(), CoinSellerAuditLog::ACTION_INVENTORY_ALLOCATED, [
                'coins' => $data->coins,
                'balance_after' => $locked->inventory_balance,
                'notes' => $data->notes,
            ]);

            return $locked->fresh(['user.profile', 'user.socialStatus', 'reviewer']);
        });
    }

    public function directSell(CoinSeller $seller, User $sellerUser, DirectSellData $data): CoinSellerSale
    {
        return DB::transaction(function () use ($seller, $sellerUser, $data): CoinSellerSale {
            $locked = CoinSeller::query()->lockForUpdate()->findOrFail($seller->getKey());
            $this->ensureActiveSeller($locked);

            if ($data->buyerId === $sellerUser->getKey()) {
                throw ValidationException::withMessages([
                    'buyer_id' => 'You cannot sell coins to yourself.',
                ]);
            }

            $buyer = User::query()->find($data->buyerId);
            if ($buyer === null || $buyer->is_guest || $buyer->status !== 'active') {
                throw ValidationException::withMessages([
                    'buyer_id' => 'The selected buyer is invalid.',
                ]);
            }

            if ($locked->inventory_balance < $data->coins) {
                throw ValidationException::withMessages([
                    'coins' => 'Insufficient seller inventory.',
                ]);
            }

            [$commissionAmount, $sellerNet] = $this->splitCommission($data->coins, (float) $locked->commission_rate);

            $locked->decrement('inventory_balance', $data->coins);
            $locked->increment('total_sold', $data->coins);
            $locked->increment('total_commission', $commissionAmount);
            $locked->increment('sales_count');
            $locked->refresh();

            $sale = CoinSellerSale::query()->create([
                'coin_seller_id' => $locked->getKey(),
                'buyer_id' => $buyer->getKey(),
                'type' => CoinSellerSale::TYPE_DIRECT_TRANSFER,
                'coins' => $data->coins,
                'price_amount' => $data->priceAmount,
                'currency' => strtoupper($data->currency),
                'commission_rate' => $locked->commission_rate,
                'commission_amount' => $commissionAmount,
                'seller_net_coins' => $sellerNet,
                'status' => CoinSellerSale::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            CoinSellerInventoryLog::query()->create([
                'coin_seller_id' => $locked->getKey(),
                'actor_id' => $sellerUser->getKey(),
                'type' => CoinSellerInventoryLog::TYPE_SALE_DEBIT,
                'amount' => -$data->coins,
                'balance_after' => $locked->inventory_balance,
                'reference_type' => $sale->getMorphClass(),
                'reference_id' => $sale->getKey(),
                'created_at' => now(),
            ]);

            CoinSellerCommission::query()->create([
                'coin_seller_id' => $locked->getKey(),
                'sale_id' => $sale->getKey(),
                'gross_coins' => $data->coins,
                'commission_rate' => $locked->commission_rate,
                'commission_amount' => $commissionAmount,
                'seller_net_coins' => $sellerNet,
                'currency' => 'coins',
                'created_at' => now(),
            ]);

            $this->wallets->applyCoinSellerCredit(
                $buyer,
                $sale,
                $data->coins,
                [
                    'seller_id' => $locked->getKey(),
                    'seller_user_id' => $sellerUser->getKey(),
                    'sale_type' => CoinSellerSale::TYPE_DIRECT_TRANSFER,
                ],
            );

            $this->writeAudit($locked, $sellerUser->getKey(), CoinSellerAuditLog::ACTION_SALE_COMPLETED, [
                'sale_id' => $sale->getKey(),
                'buyer_id' => $buyer->getKey(),
                'coins' => $data->coins,
            ]);

            return $sale->fresh(['buyer.profile', 'buyer.socialStatus', 'commission']);
        });
    }

    public function createRechargeCode(CoinSeller $seller, User $sellerUser, CreateRechargeCodeData $data): CoinRechargeCode
    {
        return DB::transaction(function () use ($seller, $sellerUser, $data): CoinRechargeCode {
            $locked = CoinSeller::query()->lockForUpdate()->findOrFail($seller->getKey());
            $this->ensureActiveSeller($locked);

            if ($locked->inventory_balance < $data->coins) {
                throw ValidationException::withMessages([
                    'coins' => 'Insufficient seller inventory.',
                ]);
            }

            $locked->decrement('inventory_balance', $data->coins);
            $locked->refresh();

            $code = CoinRechargeCode::query()->create([
                'coin_seller_id' => $locked->getKey(),
                'code' => $this->uniqueCode(),
                'coins' => $data->coins,
                'status' => CoinRechargeCode::STATUS_AVAILABLE,
                'expires_at' => $data->expiresInHours === null
                    ? null
                    : now()->addHours(max(1, $data->expiresInHours)),
            ]);

            CoinSellerInventoryLog::query()->create([
                'coin_seller_id' => $locked->getKey(),
                'actor_id' => $sellerUser->getKey(),
                'type' => CoinSellerInventoryLog::TYPE_CODE_RESERVE,
                'amount' => -$data->coins,
                'balance_after' => $locked->inventory_balance,
                'reference_type' => $code->getMorphClass(),
                'reference_id' => $code->getKey(),
                'created_at' => now(),
            ]);

            $this->writeAudit($locked, $sellerUser->getKey(), CoinSellerAuditLog::ACTION_CODE_CREATED, [
                'code_id' => $code->getKey(),
                'coins' => $data->coins,
            ]);

            return $code->fresh(['seller']);
        });
    }

    public function revokeRechargeCode(CoinRechargeCode $code, User $actor): CoinRechargeCode
    {
        return DB::transaction(function () use ($code, $actor): CoinRechargeCode {
            $lockedCode = CoinRechargeCode::query()->lockForUpdate()->findOrFail($code->getKey());

            if ($lockedCode->status !== CoinRechargeCode::STATUS_AVAILABLE) {
                throw ValidationException::withMessages([
                    'code' => 'Only available recharge codes can be revoked.',
                ]);
            }

            $seller = CoinSeller::query()->lockForUpdate()->findOrFail($lockedCode->coin_seller_id);
            $seller->increment('inventory_balance', $lockedCode->coins);
            $seller->refresh();

            $lockedCode->forceFill([
                'status' => CoinRechargeCode::STATUS_REVOKED,
                'revoked_at' => now(),
            ])->save();

            CoinSellerInventoryLog::query()->create([
                'coin_seller_id' => $seller->getKey(),
                'actor_id' => $actor->getKey(),
                'type' => CoinSellerInventoryLog::TYPE_CODE_REVOKE,
                'amount' => $lockedCode->coins,
                'balance_after' => $seller->inventory_balance,
                'reference_type' => $lockedCode->getMorphClass(),
                'reference_id' => $lockedCode->getKey(),
                'created_at' => now(),
            ]);

            $this->writeAudit($seller, $actor->getKey(), CoinSellerAuditLog::ACTION_CODE_REVOKED, [
                'code_id' => $lockedCode->getKey(),
                'coins' => $lockedCode->coins,
            ]);

            return $lockedCode->fresh(['seller']);
        });
    }

    public function redeemRechargeCode(User $buyer, RedeemRechargeCodeData $data): CoinSellerSale
    {
        return DB::transaction(function () use ($buyer, $data): CoinSellerSale {
            $normalized = strtoupper(trim($data->code));
            $code = CoinRechargeCode::query()
                ->where('code', $normalized)
                ->lockForUpdate()
                ->first();

            if ($code === null) {
                throw ValidationException::withMessages([
                    'code' => 'Recharge code is invalid.',
                ]);
            }

            if ($code->status === CoinRechargeCode::STATUS_AVAILABLE
                && $code->expires_at !== null
                && $code->expires_at->isPast()) {
                $code->forceFill(['status' => CoinRechargeCode::STATUS_EXPIRED])->save();
            }

            if ($code->status !== CoinRechargeCode::STATUS_AVAILABLE) {
                throw ValidationException::withMessages([
                    'code' => 'This recharge code is no longer available.',
                ]);
            }

            $seller = CoinSeller::query()->lockForUpdate()->findOrFail($code->coin_seller_id);

            if ($seller->user_id === $buyer->getKey()) {
                throw ValidationException::withMessages([
                    'code' => 'You cannot redeem your own recharge code.',
                ]);
            }

            if (! $seller->isActive()) {
                throw ValidationException::withMessages([
                    'code' => 'This seller account is not active.',
                ]);
            }

            [$commissionAmount, $sellerNet] = $this->splitCommission($code->coins, (float) $seller->commission_rate);

            $code->forceFill([
                'status' => CoinRechargeCode::STATUS_REDEEMED,
                'redeemed_by' => $buyer->getKey(),
                'redeemed_at' => now(),
            ])->save();

            $seller->increment('total_sold', $code->coins);
            $seller->increment('total_commission', $commissionAmount);
            $seller->increment('sales_count');

            $sale = CoinSellerSale::query()->create([
                'coin_seller_id' => $seller->getKey(),
                'buyer_id' => $buyer->getKey(),
                'recharge_code_id' => $code->getKey(),
                'type' => CoinSellerSale::TYPE_RECHARGE_CODE,
                'coins' => $code->coins,
                'price_amount' => 0,
                'currency' => 'USD',
                'commission_rate' => $seller->commission_rate,
                'commission_amount' => $commissionAmount,
                'seller_net_coins' => $sellerNet,
                'status' => CoinSellerSale::STATUS_COMPLETED,
                'completed_at' => now(),
                'metadata' => ['code' => $code->code],
            ]);

            CoinSellerCommission::query()->create([
                'coin_seller_id' => $seller->getKey(),
                'sale_id' => $sale->getKey(),
                'gross_coins' => $code->coins,
                'commission_rate' => $seller->commission_rate,
                'commission_amount' => $commissionAmount,
                'seller_net_coins' => $sellerNet,
                'currency' => 'coins',
                'created_at' => now(),
            ]);

            $this->wallets->applyCoinSellerCredit(
                $buyer,
                $sale,
                $code->coins,
                [
                    'seller_id' => $seller->getKey(),
                    'seller_user_id' => $seller->user_id,
                    'sale_type' => CoinSellerSale::TYPE_RECHARGE_CODE,
                    'code_id' => $code->getKey(),
                ],
            );

            $this->writeAudit($seller, $buyer->getKey(), CoinSellerAuditLog::ACTION_CODE_REDEEMED, [
                'sale_id' => $sale->getKey(),
                'code_id' => $code->getKey(),
                'coins' => $code->coins,
            ]);

            return $sale->fresh(['buyer.profile', 'buyer.socialStatus', 'rechargeCode', 'commission']);
        });
    }

    public function salesForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator
    {
        return CoinSellerSale::query()
            ->where('coin_seller_id', $seller->getKey())
            ->with(['buyer.profile', 'buyer.socialStatus', 'rechargeCode', 'commission'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function codesForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator
    {
        return CoinRechargeCode::query()
            ->where('coin_seller_id', $seller->getKey())
            ->with(['redeemer.profile'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function inventoryLogsForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator
    {
        return CoinSellerInventoryLog::query()
            ->where('coin_seller_id', $seller->getKey())
            ->with(['actor.profile'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function commissionsForSeller(CoinSeller $seller, int $perPage): LengthAwarePaginator
    {
        return CoinSellerCommission::query()
            ->where('coin_seller_id', $seller->getKey())
            ->with(['sale'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function listForAdmin(?string $status, int $perPage): LengthAwarePaginator
    {
        return CoinSeller::query()
            ->with(['user.profile', 'user.socialStatus', 'reviewer'])
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function dashboardStats(CoinSeller $seller): array
    {
        $availableCodes = CoinRechargeCode::query()
            ->where('coin_seller_id', $seller->getKey())
            ->where('status', CoinRechargeCode::STATUS_AVAILABLE)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return [
            'inventory_balance' => (int) $seller->inventory_balance,
            'total_sold' => (int) $seller->total_sold,
            'total_commission' => (int) $seller->total_commission,
            'sales_count' => (int) $seller->sales_count,
            'available_codes' => $availableCodes,
            'commission_rate' => (float) $seller->commission_rate,
        ];
    }

    private function ensureActiveSeller(CoinSeller $seller): void
    {
        if (! $seller->isActive()) {
            throw ValidationException::withMessages([
                'seller' => 'Only active coin sellers can perform this action.',
            ]);
        }
    }

    /** @return array{0: int, 1: int} */
    private function splitCommission(int $coins, float $rate): array
    {
        $commissionAmount = (int) floor($coins * max(0, min(100, $rate)) / 100);
        $sellerNet = $coins - $commissionAmount;

        return [$commissionAmount, $sellerNet];
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        } while (CoinRechargeCode::query()->where('code', $code)->exists());

        return $code;
    }

    /** @param  array<string, mixed>|null  $metadata */
    private function writeAudit(CoinSeller $seller, string $actorId, string $action, ?array $metadata = null): void
    {
        CoinSellerAuditLog::query()->create([
            'coin_seller_id' => $seller->getKey(),
            'actor_id' => $actorId,
            'action' => $action,
            'metadata' => $metadata === null || $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
