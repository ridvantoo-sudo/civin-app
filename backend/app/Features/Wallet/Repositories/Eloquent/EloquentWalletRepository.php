<?php

namespace App\Features\Wallet\Repositories\Eloquent;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\PkBattle\Models\PkReward;
use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
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

    public function pendingWithdrawals(int $perPage): LengthAwarePaginator
    {
        return WithdrawRequest::query()
            ->where('status', WithdrawRequest::STATUS_PENDING)
            ->with(['user.profile', 'user.socialStatus'])
            ->latest('created_at')
            ->paginate($perPage);
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
