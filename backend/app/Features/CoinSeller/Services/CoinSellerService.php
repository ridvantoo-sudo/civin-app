<?php

namespace App\Features\CoinSeller\Services;

use App\Features\Admin\Support\AdminPermission;
use App\Features\CoinSeller\DTOs\AllocateInventoryData;
use App\Features\CoinSeller\DTOs\ApplyCoinSellerData;
use App\Features\CoinSeller\DTOs\CreateRechargeCodeData;
use App\Features\CoinSeller\DTOs\DirectSellData;
use App\Features\CoinSeller\DTOs\RedeemRechargeCodeData;
use App\Features\CoinSeller\DTOs\ReviewCoinSellerData;
use App\Features\CoinSeller\Models\CoinRechargeCode;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\CoinSeller\Repositories\Contracts\CoinSellerRepository;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class CoinSellerService
{
    private const APPLY_RATE_KEY = 'coin-seller-apply:%s';

    private const SELL_RATE_KEY = 'coin-seller-sell:%s';

    private const CODE_RATE_KEY = 'coin-seller-code:%s';

    private const REDEEM_RATE_KEY = 'coin-seller-redeem:%s';

    private const RATE_MAX = 10;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private CoinSellerRepository $sellers,
        private WalletRepository $wallets,
    ) {}

    public function me(User $user): ?CoinSeller
    {
        $this->ensureEligible($user);

        return $this->sellers->findByUser($user);
    }

    public function dashboard(User $user): array
    {
        $seller = $this->requireOwnSeller($user);

        return [
            'seller' => $seller,
            'stats' => $this->sellers->dashboardStats($seller),
        ];
    }

    public function apply(User $user, ApplyCoinSellerData $data): CoinSeller
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::APPLY_RATE_KEY, $user->getKey()), 'application');

        return $this->sellers->apply($user, $data);
    }

    public function review(CoinSeller $seller, User $reviewer, ReviewCoinSellerData $data): CoinSeller
    {
        $this->ensureAdmin($reviewer);

        return $this->sellers->review($seller, $reviewer, $data);
    }

    public function allocateInventory(CoinSeller $seller, User $actor, AllocateInventoryData $data): CoinSeller
    {
        $this->ensureAdmin($actor);

        return $this->sellers->allocateInventory($seller, $actor, $data);
    }

    public function directSell(User $user, DirectSellData $data): CoinSellerSale
    {
        $seller = $this->requireOwnActiveSeller($user);
        $this->enforceRateLimit(sprintf(self::SELL_RATE_KEY, $user->getKey()), 'sale');

        $sale = $this->sellers->directSell($seller, $user, $data);
        $buyer = User::query()->findOrFail($data->buyerId);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($buyer)->fresh());

        return $sale;
    }

    public function createRechargeCode(User $user, CreateRechargeCodeData $data): CoinRechargeCode
    {
        $seller = $this->requireOwnActiveSeller($user);
        $this->enforceRateLimit(sprintf(self::CODE_RATE_KEY, $user->getKey()), 'code');

        return $this->sellers->createRechargeCode($seller, $user, $data);
    }

    public function revokeRechargeCode(User $user, CoinRechargeCode $code): CoinRechargeCode
    {
        $seller = $this->requireOwnActiveSeller($user);

        if ($code->coin_seller_id !== $seller->getKey()) {
            throw new AuthorizationException('You can only revoke your own recharge codes.');
        }

        return $this->sellers->revokeRechargeCode($code, $user);
    }

    public function redeemRechargeCode(User $user, RedeemRechargeCodeData $data): CoinSellerSale
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::REDEEM_RATE_KEY, $user->getKey()), 'redeem');

        $sale = $this->sellers->redeemRechargeCode($user, $data);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $sale;
    }

    public function sales(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->sellers->salesForSeller($this->requireOwnSeller($user), max(1, min($perPage, 100)));
    }

    public function codes(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->sellers->codesForSeller($this->requireOwnSeller($user), max(1, min($perPage, 100)));
    }

    public function inventoryLogs(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->sellers->inventoryLogsForSeller($this->requireOwnSeller($user), max(1, min($perPage, 100)));
    }

    public function commissions(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->sellers->commissionsForSeller($this->requireOwnSeller($user), max(1, min($perPage, 100)));
    }

    public function listForAdmin(User $admin, ?string $status, int $perPage): LengthAwarePaginator
    {
        $this->ensureAdmin($admin);

        return $this->sellers->listForAdmin($status, max(1, min($perPage, 100)));
    }

    private function requireOwnSeller(User $user): CoinSeller
    {
        $this->ensureEligible($user);
        $seller = $this->sellers->findByUser($user);

        if ($seller === null) {
            throw ValidationException::withMessages([
                'seller' => 'You do not have a coin seller account.',
            ]);
        }

        return $seller;
    }

    private function requireOwnActiveSeller(User $user): CoinSeller
    {
        $seller = $this->requireOwnSeller($user);

        if (! $seller->isActive()) {
            throw ValidationException::withMessages([
                'seller' => 'Your coin seller account is not active.',
            ]);
        }

        return $seller;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can use the coin seller system.');
        }
    }

    private function ensureAdmin(User $user): void
    {
        if ($user->is_admin || $user->can(AdminPermission::MANAGE_COIN_SELLERS)) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage coin sellers.');
    }

    private function enforceRateLimit(string $key, string $action): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages([
                $action => 'You are performing coin seller actions too quickly.',
            ]);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
