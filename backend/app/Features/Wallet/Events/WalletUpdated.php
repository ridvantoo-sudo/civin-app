<?php

namespace App\Features\Wallet\Events;

use App\Features\Wallet\Models\Wallet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WalletUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Wallet $wallet) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.wallet.'.$this->wallet->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'wallet.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->wallet->user_id,
            'coins_balance' => $this->wallet->coins_balance,
            'diamonds_balance' => $this->wallet->diamonds_balance,
            'updated_at' => $this->wallet->updated_at?->toISOString(),
        ];
    }
}
