<?php

namespace App\Features\Security\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Advisory notice that the security platform has recorded a wallet-scoped
 * restriction. The Wallet module is not modified and remains authoritative for
 * its own balances; consumers read the restriction from the security API.
 */
final class WalletLocked implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $reason,
        public readonly ?string $banId = null,
        public readonly ?string $expiresAt = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel((string) config('security_platform.realtime.channel', 'security.monitor')),
            new PrivateChannel('user.security.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.wallet_locked';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'ban_id' => $this->banId,
            'expires_at' => $this->expiresAt,
        ];
    }
}
