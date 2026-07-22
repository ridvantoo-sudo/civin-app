<?php

namespace App\Features\Wallet\Repositories\Contracts;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\PkBattle\Models\PkReward;
use App\Features\Users\Models\User;
use App\Features\Wallet\DTOs\RechargeWalletData;
use App\Features\Wallet\DTOs\RequestWithdrawData;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\Models\RechargeOrder;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WithdrawRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WalletRepository
{
    public function createForUser(User $user, int $coins = 0, int $diamonds = 0): Wallet;

    public function findByUser(User $user): ?Wallet;

    public function findOrCreateForUser(User $user): Wallet;

    public function lockForUser(User $user): Wallet;

    public function transactionsForUser(User $user, int $perPage): LengthAwarePaginator;

    public function recharge(User $user, RechargeWalletData $data): RechargeOrder;

    public function requestWithdraw(User $user, RequestWithdrawData $data): WithdrawRequest;

    public function reviewWithdraw(WithdrawRequest $request, User $reviewer, ReviewWithdrawData $data): WithdrawRequest;

    public function applyGiftTransfer(User $sender, User $receiver, GiftTransaction $giftTransaction, int $coins): array;

    public function applyPkReward(User $winner, PkReward $reward, int $amount): Wallet;

    /** @return list<WithdrawRequest> */
    public function pendingWithdrawals(int $perPage): LengthAwarePaginator;
}
