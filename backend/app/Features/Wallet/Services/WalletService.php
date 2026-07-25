<?php

namespace App\Features\Wallet\Services;

use App\Features\Kyc\Services\KycService;
use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\DTOs\ReviewOfflineRechargeData;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\DTOs\SubmitOfflineRechargeData;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Models\RechargeOrder;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Notifications\OfflineRechargeReviewedNotification;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class WalletService
{
    private const RECHARGE_RATE_KEY = 'wallet-recharge:%s';

    private const RECHARGE_RATE_MAX = 10;

    private const WITHDRAW_RATE_KEY = 'wallet-withdraw:%s';

    private const WITHDRAW_RATE_MAX = 5;

    private const OFFLINE_RECHARGE_RATE_KEY = 'wallet-offline-recharge:%s';

    private const OFFLINE_RECHARGE_RATE_MAX = 5;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private WalletRepository $wallets,
        private KycService $kyc,
    ) {}

    public function createForUser(User $user): Wallet
    {
        return $this->wallets->createForUser($user);
    }

    public function getForUser(User $actor, User $subject): Wallet
    {
        if ($actor->getKey() !== $subject->getKey()) {
            throw new AuthorizationException('You can only view your own wallet.');
        }

        return $this->wallets->findOrCreateForUser($subject);
    }

    public function transactions(User $actor, User $subject, int $perPage): LengthAwarePaginator
    {
        if ($actor->getKey() !== $subject->getKey()) {
            throw new AuthorizationException('You can only view your own wallet transactions.');
        }

        $this->wallets->findOrCreateForUser($subject);

        return $this->wallets->transactionsForUser($subject, $perPage);
    }

    public function recharge(User $user, RechargeWalletData $data): RechargeOrder
    {
        $this->enforceRateLimit(sprintf(self::RECHARGE_RATE_KEY, $user->getKey()), self::RECHARGE_RATE_MAX, 'recharge');

        $order = $this->wallets->recharge($user, $data);

        if ($order->wasRecentlyCreated) {
            WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());
        }

        return $order;
    }

    public function withdraw(User $user, RequestWithdrawData $data): WithdrawRequest
    {
        if (! $this->kyc->isWithdrawalVerified($user)) {
            throw ValidationException::withMessages([
                'kyc' => 'Complete KYC verification before requesting a withdrawal.',
            ]);
        }

        $this->enforceRateLimit(sprintf(self::WITHDRAW_RATE_KEY, $user->getKey()), self::WITHDRAW_RATE_MAX, 'withdraw');

        return $this->wallets->requestWithdraw($user, $data);
    }

    public function reviewWithdraw(WithdrawRequest $request, User $reviewer, ReviewWithdrawData $data): WithdrawRequest
    {
        $reviewed = $this->wallets->reviewWithdraw($request, $reviewer, $data);

        if ($reviewed->status === WithdrawRequest::STATUS_APPROVED) {
            $wallet = $this->wallets->findOrCreateForUser($reviewed->user);
            WalletUpdated::dispatch($wallet->fresh());
        }

        return $reviewed;
    }

    public function pendingWithdrawals(int $perPage): LengthAwarePaginator
    {
        return $this->wallets->pendingWithdrawals($perPage);
    }

    public function submitOfflineRecharge(User $user, SubmitOfflineRechargeData $data): OfflineRechargeRequest
    {
        $this->enforceRateLimit(
            sprintf(self::OFFLINE_RECHARGE_RATE_KEY, $user->getKey()),
            self::OFFLINE_RECHARGE_RATE_MAX,
            'offline_recharge',
        );

        return $this->wallets->submitOfflineRecharge($user, $data);
    }

    public function reviewOfflineRecharge(
        OfflineRechargeRequest $request,
        User $reviewer,
        ReviewOfflineRechargeData $data,
    ): OfflineRechargeRequest {
        $reviewed = $this->wallets->reviewOfflineRecharge($request, $reviewer, $data);

        if ($reviewed->status === OfflineRechargeRequest::STATUS_APPROVED) {
            $wallet = $this->wallets->findOrCreateForUser($reviewed->user);
            WalletUpdated::dispatch($wallet->fresh());
        }

        $reviewed->user->notify(new OfflineRechargeReviewedNotification($reviewed));

        return $reviewed;
    }

    public function offlineRechargesForUser(User $actor, User $subject, int $perPage): LengthAwarePaginator
    {
        if ($actor->getKey() !== $subject->getKey()) {
            throw new AuthorizationException('You can only view your own offline recharge requests.');
        }

        return $this->wallets->offlineRechargesForUser($subject, $perPage);
    }

    public function pendingOfflineRecharges(int $perPage): LengthAwarePaginator
    {
        return $this->wallets->pendingOfflineRecharges($perPage);
    }

    private function enforceRateLimit(string $rateKey, int $maxAttempts, string $field): void
    {
        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            throw ValidationException::withMessages([
                $field => 'Too many wallet requests. Please try again later.',
            ]);
        }

        RateLimiter::hit($rateKey, self::RATE_DECAY_SECONDS);
    }
}
