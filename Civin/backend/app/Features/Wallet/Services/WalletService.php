<?php

namespace App\Features\Wallet\Services;

use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\RechargeOrder;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use App\Features\Wallet\Support\PaymentProofVerifier;
use App\Features\Wallet\Support\RechargeCatalog;
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

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private WalletRepository $wallets,
        private PaymentProofVerifier $payments,
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

        if (RechargeCatalog::find($data->packageId) === null) {
            throw ValidationException::withMessages([
                'package_id' => 'Unknown recharge package.',
            ]);
        }

        $this->payments->assertVerified($data->paymentProvider, $data->transactionId);

        $order = $this->wallets->recharge($user, $data);

        if ($order->wasRecentlyCreated) {
            WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());
        }

        return $order;
    }

    public function withdraw(User $user, RequestWithdrawData $data): WithdrawRequest
    {
        $this->enforceRateLimit(sprintf(self::WITHDRAW_RATE_KEY, $user->getKey()), self::WITHDRAW_RATE_MAX, 'withdraw');

        $request = $this->wallets->requestWithdraw($user, $data);
        WalletUpdated::dispatch($this->wallets->findOrCreateForUser($user)->fresh());

        return $request;
    }

    public function reviewWithdraw(WithdrawRequest $request, User $reviewer, ReviewWithdrawData $data): WithdrawRequest
    {
        $reviewed = $this->wallets->reviewWithdraw($request, $reviewer, $data);

        if (in_array($reviewed->status, [WithdrawRequest::STATUS_APPROVED, WithdrawRequest::STATUS_REJECTED], true)) {
            $wallet = $this->wallets->findOrCreateForUser($reviewed->user);
            WalletUpdated::dispatch($wallet->fresh());
        }

        return $reviewed;
    }

    public function pendingWithdrawals(int $perPage): LengthAwarePaginator
    {
        return $this->wallets->pendingWithdrawals($perPage);
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
