<?php

namespace App\Features\Wallet\Repositories\Eloquent;

use App\Features\Challenges\Models\UserChallengeProgress;
use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\PkBattle\Models\PkReward;
use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\DTOs\ReviewOfflineRechargeData;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\DTOs\SubmitOfflineRechargeData;
use App\Features\Wallet\Models\OfflineRechargeAuditLog;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Models\RechargeOrder;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentWalletRepository implements WalletRepository
{
    public function createForUser(User $user, int $coins = 0, int $diamonds = 0): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->getKey(),
            'coins_balance' => max(0, $coins),
            'diamonds_balance' => max(0, $diamonds),
        ]);
    }

    public function findByUser(User $user): ?Wallet
    {
        return Wallet::query()->where('user_id', $user->getKey())->first();
    }

    public function findOrCreateForUser(User $user): Wallet
    {
        return $this->findByUser($user) ?? $this->createForUser($user);
    }

    public function lockForUser(User $user): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if ($wallet === null) {
            $this->createForUser($user);

            $wallet = Wallet::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $wallet;
    }

    public function transactionsForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return WalletTransaction::query()
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function recharge(User $user, RechargeWalletData $data): RechargeOrder
    {
        return DB::transaction(function () use ($user, $data): RechargeOrder {
            $existing = RechargeOrder::query()
                ->where('payment_provider', $data->paymentProvider)
                ->where('transaction_id', $data->transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->user_id !== $user->getKey()) {
                    throw ValidationException::withMessages([
                        'transaction_id' => 'This payment transaction belongs to another account.',
                    ]);
                }

                return $existing;
            }

            $order = RechargeOrder::query()->create([
                'user_id' => $user->getKey(),
                'package_name' => $data->packageName,
                'coins' => $data->coins,
                'price' => $data->price,
                'currency' => strtoupper($data->currency),
                'status' => RechargeOrder::STATUS_PENDING,
                'payment_provider' => $data->paymentProvider,
                'transaction_id' => $data->transactionId,
                'created_at' => now(),
            ]);

            $wallet = $this->lockForUser($user);
            $wallet->increment('coins_balance', $data->coins);

            $this->audit(
                userId: $user->getKey(),
                type: WalletTransaction::TYPE_COIN_PURCHASE,
                amount: $data->coins,
                currency: WalletTransaction::CURRENCY_COINS,
                referenceType: $order->getMorphClass(),
                referenceId: $order->getKey(),
                metadata: array_filter([
                    'package_name' => $data->packageName,
                    'price' => $data->price,
                    'currency' => strtoupper($data->currency),
                    'payment_provider' => $data->paymentProvider,
                    'transaction_id' => $data->transactionId,
                    ...($data->metadata ?? []),
                ], fn ($value) => $value !== null),
            );

            $order->forceFill(['status' => RechargeOrder::STATUS_COMPLETED])->save();

            return $order;
        });
    }

    public function requestWithdraw(User $user, RequestWithdrawData $data): WithdrawRequest
    {
        return DB::transaction(function () use ($user, $data): WithdrawRequest {
            $wallet = $this->lockForUser($user);

            if ($wallet->diamonds_balance < $data->diamonds) {
                throw ValidationException::withMessages([
                    'diamonds' => 'Insufficient diamond balance.',
                ]);
            }

            return WithdrawRequest::query()->create([
                'user_id' => $user->getKey(),
                'diamonds' => $data->diamonds,
                'amount' => $data->amount,
                'status' => WithdrawRequest::STATUS_PENDING,
                'approved_by' => null,
                'created_at' => now(),
            ]);
        });
    }

    public function reviewWithdraw(WithdrawRequest $request, User $reviewer, ReviewWithdrawData $data): WithdrawRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $data): WithdrawRequest {
            $locked = WithdrawRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status !== WithdrawRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending withdrawal requests can be reviewed.',
                ]);
            }

            if ($data->status === WithdrawRequest::STATUS_REJECTED) {
                $locked->forceFill([
                    'status' => WithdrawRequest::STATUS_REJECTED,
                    'approved_by' => $reviewer->getKey(),
                ])->save();

                return $locked->fresh(['user', 'approver']);
            }

            if ($data->status !== WithdrawRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Withdrawal review status is invalid.',
                ]);
            }

            $owner = User::query()->findOrFail($locked->user_id);
            $wallet = $this->lockForUser($owner);

            if ($wallet->diamonds_balance < $locked->diamonds) {
                throw ValidationException::withMessages([
                    'diamonds' => 'Insufficient diamond balance to approve this withdrawal.',
                ]);
            }

            $wallet->decrement('diamonds_balance', $locked->diamonds);

            $this->audit(
                userId: $owner->getKey(),
                type: WalletTransaction::TYPE_WITHDRAW,
                amount: -$locked->diamonds,
                currency: WalletTransaction::CURRENCY_DIAMONDS,
                referenceType: $locked->getMorphClass(),
                referenceId: $locked->getKey(),
                metadata: array_filter([
                    'amount' => $locked->amount,
                    'approved_by' => $reviewer->getKey(),
                    'notes' => $data->notes,
                ], fn ($value) => $value !== null),
            );

            $locked->forceFill([
                'status' => WithdrawRequest::STATUS_APPROVED,
                'approved_by' => $reviewer->getKey(),
            ])->save();

            return $locked->fresh(['user', 'approver']);
        });
    }

    public function applyGiftTransfer(User $sender, User $receiver, GiftTransaction $giftTransaction, int $coins): array
    {
        $senderWallet = $this->lockForUser($sender);

        if ($senderWallet->coins_balance < $coins) {
            throw ValidationException::withMessages(['coins' => 'Insufficient coin balance.']);
        }

        $receiverWallet = $this->lockForUser($receiver);

        $senderWallet->decrement('coins_balance', $coins);
        $receiverWallet->increment('diamonds_balance', $coins);

        $this->audit(
            userId: $sender->getKey(),
            type: WalletTransaction::TYPE_GIFT_SENT,
            amount: -$coins,
            currency: WalletTransaction::CURRENCY_COINS,
            referenceType: $giftTransaction->getMorphClass(),
            referenceId: $giftTransaction->getKey(),
            metadata: [
                'receiver_id' => $receiver->getKey(),
                'gift_id' => $giftTransaction->gift_id,
                'quantity' => $giftTransaction->quantity,
                'room_id' => $giftTransaction->room_id,
            ],
        );

        $this->audit(
            userId: $receiver->getKey(),
            type: WalletTransaction::TYPE_GIFT_RECEIVED,
            amount: $coins,
            currency: WalletTransaction::CURRENCY_DIAMONDS,
            referenceType: $giftTransaction->getMorphClass(),
            referenceId: $giftTransaction->getKey(),
            metadata: [
                'sender_id' => $sender->getKey(),
                'gift_id' => $giftTransaction->gift_id,
                'quantity' => $giftTransaction->quantity,
                'room_id' => $giftTransaction->room_id,
            ],
        );

        return [
            'sender' => $senderWallet->fresh(),
            'receiver' => $receiverWallet->fresh(),
        ];
    }

    public function applyPkReward(User $winner, PkReward $reward, int $amount): Wallet
    {
        $wallet = $this->lockForUser($winner);
        $wallet->increment('diamonds_balance', $amount);

        $this->audit(
            userId: $winner->getKey(),
            type: WalletTransaction::TYPE_PK_REWARD,
            amount: $amount,
            currency: WalletTransaction::CURRENCY_DIAMONDS,
            referenceType: $reward->getMorphClass(),
            referenceId: $reward->getKey(),
            metadata: [
                'pk_battle_id' => $reward->pk_battle_id,
                'reward_type' => $reward->reward_type,
            ],
        );

        return $wallet->fresh();
    }

    public function applyChallengeReward(
        User $user,
        UserChallengeProgress $progress,
        int $coins,
        int $diamonds,
    ): Wallet {
        $wallet = $this->lockForUser($user);
        $coins = max(0, $coins);
        $diamonds = max(0, $diamonds);

        if ($coins > 0) {
            $wallet->increment('coins_balance', $coins);
            $this->audit(
                userId: $user->getKey(),
                type: WalletTransaction::TYPE_CHALLENGE_REWARD,
                amount: $coins,
                currency: WalletTransaction::CURRENCY_COINS,
                referenceType: $progress->getMorphClass(),
                referenceId: $progress->getKey(),
                metadata: [
                    'challenge_id' => $progress->challenge_id,
                    'period_key' => $progress->period_key,
                    'reward' => 'coins',
                ],
            );
        }

        if ($diamonds > 0) {
            $wallet->increment('diamonds_balance', $diamonds);
            $this->audit(
                userId: $user->getKey(),
                type: WalletTransaction::TYPE_CHALLENGE_REWARD,
                amount: $diamonds,
                currency: WalletTransaction::CURRENCY_DIAMONDS,
                referenceType: $progress->getMorphClass(),
                referenceId: $progress->getKey(),
                metadata: [
                    'challenge_id' => $progress->challenge_id,
                    'period_key' => $progress->period_key,
                    'reward' => 'diamonds',
                ],
            );
        }

        return $wallet->fresh() ?? $wallet;
    }

    public function applyCoinSellerCredit(
        User $buyer,
        CoinSellerSale $sale,
        int $coins,
        ?array $metadata = null,
    ): Wallet {
        $wallet = $this->lockForUser($buyer);
        $wallet->increment('coins_balance', $coins);

        $this->audit(
            userId: $buyer->getKey(),
            type: WalletTransaction::TYPE_COIN_SELLER_PURCHASE,
            amount: $coins,
            currency: WalletTransaction::CURRENCY_COINS,
            referenceType: $sale->getMorphClass(),
            referenceId: $sale->getKey(),
            metadata: $metadata,
        );

        return $wallet->fresh() ?? $wallet;
    }

    public function applyGrowthReward(
        User $user,
        \Illuminate\Database\Eloquent\Model $source,
        int $coins,
        int $diamonds = 0,
        ?array $metadata = null,
    ): Wallet {
        $wallet = $this->lockForUser($user);
        $coins = max(0, $coins);
        $diamonds = max(0, $diamonds);

        if ($coins > 0) {
            $wallet->increment('coins_balance', $coins);
            $this->audit(
                userId: $user->getKey(),
                type: WalletTransaction::TYPE_GROWTH_REWARD,
                amount: $coins,
                currency: WalletTransaction::CURRENCY_COINS,
                referenceType: $source->getMorphClass(),
                referenceId: $source->getKey(),
                metadata: $metadata,
            );
        }

        if ($diamonds > 0) {
            $wallet->increment('diamonds_balance', $diamonds);
            $this->audit(
                userId: $user->getKey(),
                type: WalletTransaction::TYPE_GROWTH_REWARD,
                amount: $diamonds,
                currency: WalletTransaction::CURRENCY_DIAMONDS,
                referenceType: $source->getMorphClass(),
                referenceId: $source->getKey(),
                metadata: $metadata,
            );
        }

        return $wallet->fresh() ?? $wallet;
    }

    public function pendingWithdrawals(int $perPage): LengthAwarePaginator
    {
        return WithdrawRequest::query()
            ->where('status', WithdrawRequest::STATUS_PENDING)
            ->with(['user.profile', 'user.socialStatus'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function submitOfflineRecharge(User $user, SubmitOfflineRechargeData $data): OfflineRechargeRequest
    {
        return DB::transaction(function () use ($user, $data): OfflineRechargeRequest {
            $duplicate = OfflineRechargeRequest::query()
                ->where('user_id', $user->getKey())
                ->where('reference_number', $data->referenceNumber)
                ->whereIn('status', [
                    OfflineRechargeRequest::STATUS_PENDING,
                    OfflineRechargeRequest::STATUS_APPROVED,
                ])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference_number' => 'This payment reference has already been submitted.',
                ]);
            }

            $request = OfflineRechargeRequest::query()->create([
                'user_id' => $user->getKey(),
                'coins' => $data->coins,
                'amount' => $data->amount,
                'currency' => strtoupper($data->currency),
                'reference_number' => $data->referenceNumber,
                'receipt_path' => $data->receiptPath,
                'status' => OfflineRechargeRequest::STATUS_PENDING,
                'approved_by' => null,
                'admin_notes' => null,
                'reviewed_at' => null,
                'created_at' => now(),
            ]);

            $this->writeOfflineAudit(
                request: $request,
                actorId: $user->getKey(),
                action: OfflineRechargeAuditLog::ACTION_SUBMITTED,
                metadata: array_filter([
                    'coins' => $data->coins,
                    'amount' => $data->amount,
                    'currency' => strtoupper($data->currency),
                    'reference_number' => $data->referenceNumber,
                    ...($data->metadata ?? []),
                ], fn ($value) => $value !== null),
            );

            return $request->fresh(['user', 'approver']);
        });
    }

    public function reviewOfflineRecharge(
        OfflineRechargeRequest $request,
        User $reviewer,
        ReviewOfflineRechargeData $data,
    ): OfflineRechargeRequest {
        return DB::transaction(function () use ($request, $reviewer, $data): OfflineRechargeRequest {
            $locked = OfflineRechargeRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status !== OfflineRechargeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending offline recharge requests can be reviewed.',
                ]);
            }

            if ($data->status === OfflineRechargeRequest::STATUS_REJECTED) {
                $locked->forceFill([
                    'status' => OfflineRechargeRequest::STATUS_REJECTED,
                    'approved_by' => $reviewer->getKey(),
                    'admin_notes' => $data->notes,
                    'reviewed_at' => now(),
                ])->save();

                $this->writeOfflineAudit(
                    request: $locked,
                    actorId: $reviewer->getKey(),
                    action: OfflineRechargeAuditLog::ACTION_REJECTED,
                    metadata: array_filter([
                        'notes' => $data->notes,
                    ], fn ($value) => $value !== null),
                );

                return $locked->fresh(['user', 'approver']);
            }

            if ($data->status !== OfflineRechargeRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Offline recharge review status is invalid.',
                ]);
            }

            $owner = User::query()->findOrFail($locked->user_id);
            $wallet = $this->lockForUser($owner);
            $wallet->increment('coins_balance', $locked->coins);

            $this->audit(
                userId: $owner->getKey(),
                type: WalletTransaction::TYPE_OFFLINE_RECHARGE,
                amount: $locked->coins,
                currency: WalletTransaction::CURRENCY_COINS,
                referenceType: $locked->getMorphClass(),
                referenceId: $locked->getKey(),
                metadata: array_filter([
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'reference_number' => $locked->reference_number,
                    'approved_by' => $reviewer->getKey(),
                    'notes' => $data->notes,
                ], fn ($value) => $value !== null),
            );

            $locked->forceFill([
                'status' => OfflineRechargeRequest::STATUS_APPROVED,
                'approved_by' => $reviewer->getKey(),
                'admin_notes' => $data->notes,
                'reviewed_at' => now(),
            ])->save();

            $this->writeOfflineAudit(
                request: $locked,
                actorId: $reviewer->getKey(),
                action: OfflineRechargeAuditLog::ACTION_APPROVED,
                metadata: array_filter([
                    'coins' => $locked->coins,
                    'notes' => $data->notes,
                ], fn ($value) => $value !== null),
            );

            return $locked->fresh(['user', 'approver']);
        });
    }

    public function offlineRechargesForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return OfflineRechargeRequest::query()
            ->where('user_id', $user->getKey())
            ->with(['approver'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function pendingOfflineRecharges(int $perPage): LengthAwarePaginator
    {
        return OfflineRechargeRequest::query()
            ->where('status', OfflineRechargeRequest::STATUS_PENDING)
            ->with(['user.profile', 'user.socialStatus'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function writeOfflineAudit(
        OfflineRechargeRequest $request,
        string $actorId,
        string $action,
        ?array $metadata,
    ): OfflineRechargeAuditLog {
        return OfflineRechargeAuditLog::query()->create([
            'offline_recharge_request_id' => $request->getKey(),
            'actor_id' => $actorId,
            'action' => $action,
            'metadata' => $metadata === null || $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function audit(
        string $userId,
        string $type,
        int $amount,
        string $currency,
        ?string $referenceType,
        ?string $referenceId,
        ?array $metadata,
    ): WalletTransaction {
        return WalletTransaction::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata === null || $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
