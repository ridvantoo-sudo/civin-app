<?php

namespace App\Features\Wallet\Events;

use App\Features\Wallet\Models\Wallet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GiftBalanceChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Wallet $wallet,
        public readonly string $direction,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $giftTransactionId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.wallet.'.$this->wallet->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'gift.balance.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->wallet->user_id,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'gift_transaction_id' => $this->giftTransactionId,
            'coins_balance' => $this->wallet->coins_balance,
            'diamonds_balance' => $this->wallet->diamonds_balance,
        ];
    }
}
